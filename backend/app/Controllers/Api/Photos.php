<?php

namespace App\Controllers\Api;

use App\Models\FriendshipModel;
use App\Models\NotificationModel;
use App\Models\PhotoCommentModel;
use App\Models\PhotoTagModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Etiquetas y comentarios de las fotos. Quien puede ver una foto (su dueño
 * y los amigos de este) puede comentarla y etiquetarse o etiquetar a sus
 * amigos que también puedan verla.
 */
class Photos extends ApiController
{
    private const MAX_PHOTO_TAGS = 30;

    /**
     * Foto con sus etiquetas y comentarios. Marca como leídos los avisos
     * que el usuario tenía sobre ella.
     */
    public function show(int $id): ResponseInterface
    {
        $photo = $this->viewablePhoto($id);
        if ($photo === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $viewerId = (int) auth()->user()->id;
        model(NotificationModel::class)
            ->where('user_id', $viewerId)
            ->where('photo_id', $id)
            ->where('read_at', null)
            ->set('read_at', date('Y-m-d H:i:s'))
            ->update();

        return $this->respond([
            'photo' => [
                ...$this->photoData($photo),
                'album_title' => $photo['album_title'],
                'owner_id'    => (int) $photo['owner_id'],
            ],
            'tags'     => $this->tagList($photo),
            'comments' => $this->commentList($photo),
        ]);
    }

    /**
     * Personas que el usuario puede etiquetar en la foto: él mismo y los amigos
     * suyos que pueden verla, salvo los que ya están etiquetados.
     */
    public function taggable(int $id): ResponseInterface
    {
        $photo = $this->viewablePhoto($id);
        if ($photo === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $viewerId  = (int) auth()->user()->id;
        $ownerId   = (int) $photo['owner_id'];
        $tagged    = array_map('intval', array_column(model(PhotoTagModel::class)->select('user_id')->where('photo_id', $id)->findAll(), 'user_id'));
        $ownerSide = $this->photoOwnersVisibleTo($ownerId);
        $ids       = array_values(array_filter(
            $this->photoOwnersVisibleTo($viewerId),
            static fn (int $userId): bool => in_array($userId, $ownerSide, true) && ! in_array($userId, $tagged, true),
        ));

        if ($ids === []) {
            return $this->respond(['people' => []]);
        }

        $rows = db_connect()->table('users')
            ->select(self::PERSON_COLUMNS)
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->whereIn('users.id', $ids)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get()
            ->getResultArray();

        return $this->respond(['people' => array_map(fn (array $row): array => $this->personData($row, true), $rows)]);
    }

    public function tag(int $id): ResponseInterface
    {
        $photo = $this->viewablePhoto($id);
        if ($photo === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $taggerId = (int) auth()->user()->id;
        if (($limit = $this->throttle("photo-tag-{$taggerId}", 120, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $rules = [
            'user_id' => ['label' => 'Persona', 'rules' => 'required|is_natural_no_zero'],
            'x'       => ['label' => 'Posición', 'rules' => 'required|decimal|greater_than_equal_to[0]|less_than[100]'],
            'y'       => ['label' => 'Posición', 'rules' => 'required|decimal|greater_than_equal_to[0]|less_than[100]'],
            'width'   => ['label' => 'Ancho', 'rules' => 'required|decimal|greater_than_equal_to[2]|less_than_equal_to[100]'],
            'height'  => ['label' => 'Alto', 'rules' => 'required|decimal|greater_than_equal_to[2]|less_than_equal_to[100]'],
        ];
        if (! $this->validateData($input, $rules)) {
            return $this->invalid($this->validator->getErrors());
        }

        $area = [];

        foreach (['x', 'y', 'width', 'height'] as $field) {
            $area[$field] = round((float) $input[$field], 2);
        }
        if ($area['x'] + $area['width'] > 100 || $area['y'] + $area['height'] > 100) {
            return $this->invalid(['width' => 'La zona etiquetada tiene que quedar dentro de la foto.']);
        }

        $userId = (int) $input['user_id'];
        if (! $this->canTag($taggerId, $userId, (int) $photo['owner_id'])) {
            return $this->message('Solo puedes etiquetarte a ti o a tus amigos que pueden ver la foto.', 403);
        }

        $tags = model(PhotoTagModel::class);
        if ($tags->where('photo_id', $id)->where('user_id', $userId)->countAllResults() > 0) {
            return $this->message('Esta persona ya está etiquetada en la foto.', 409);
        }
        if ($tags->where('photo_id', $id)->countAllResults() >= self::MAX_PHOTO_TAGS) {
            return $this->message('Una foto puede tener como máximo ' . self::MAX_PHOTO_TAGS . ' etiquetas.', 422);
        }

        $tagId = $tags->insert(['photo_id' => $id, 'user_id' => $userId, 'tagger_id' => $taggerId, ...$area]);
        if ($tagId === false) {
            return $this->message('No se ha podido guardar la etiqueta.', 500);
        }
        model(NotificationModel::class)->notify($userId, $taggerId, 'photo_tag', ['photo_id' => $id, 'photo_tag_id' => (int) $tagId]);

        return $this->respondCreated(['tags' => $this->tagList($photo)]);
    }

    /**
     * Quitan una etiqueta la persona etiquetada, quien la puso y el dueño de la foto.
     */
    public function untag(int $tagId): ResponseInterface
    {
        $tag   = model(PhotoTagModel::class)->find($tagId);
        $photo = $tag === null ? null : $this->viewablePhoto((int) $tag['photo_id']);
        if ($tag === null || $photo === null || ! $this->canRemoveTag($tag, $photo)) {
            return $this->message('No se ha encontrado la etiqueta.', 404);
        }

        model(PhotoTagModel::class)->delete($tagId);

        return $this->respond(['message' => 'Etiqueta quitada.', 'tags' => $this->tagList($photo)]);
    }

    public function comment(int $id): ResponseInterface
    {
        $photo = $this->viewablePhoto($id);
        if ($photo === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $authorId = (int) auth()->user()->id;
        if (($limit = $this->throttle("photo-comment-{$authorId}", 60, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $data  = ['body' => is_string($input['body'] ?? null) ? trim($input['body']) : ''];
        if (! $this->validateData($data, ['body' => ['label' => 'Comentario', 'rules' => 'required|max_length[500]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $commentId = model(PhotoCommentModel::class)->insert(['photo_id' => $id, 'user_id' => $authorId, ...$data]);
        if ($commentId === false) {
            return $this->message('No se ha podido publicar el comentario.', 500);
        }

        // Avisa al dueño de la foto y a quienes salen en ella, si todavía pueden verla
        $ownerId    = (int) $photo['owner_id'];
        $recipients = array_unique([
            $ownerId,
            ...array_map('intval', array_column(model(PhotoTagModel::class)->select('user_id')->where('photo_id', $id)->findAll(), 'user_id')),
        ]);
        $notifications = model(NotificationModel::class);

        foreach ($recipients as $recipientId) {
            if ($this->canSeePhotosOf($ownerId, $recipientId)) {
                $notifications->notify($recipientId, $authorId, 'photo_comment', ['photo_id' => $id, 'photo_comment_id' => (int) $commentId]);
            }
        }

        return $this->respondCreated(['comments' => $this->commentList($photo)]);
    }

    /**
     * Borran un comentario su autor y el dueño de la foto.
     */
    public function deleteComment(int $commentId): ResponseInterface
    {
        $comment = model(PhotoCommentModel::class)->find($commentId);
        $photo   = $comment === null ? null : $this->viewablePhoto((int) $comment['photo_id']);
        $userId  = (int) auth()->user()->id;
        if ($comment === null || $photo === null || ($userId !== (int) $comment['user_id'] && $userId !== (int) $photo['owner_id'])) {
            return $this->message('No se ha encontrado el comentario.', 404);
        }

        model(PhotoCommentModel::class)->delete($commentId);

        return $this->respond(['message' => 'Comentario borrado.', 'comments' => $this->commentList($photo)]);
    }

    /**
     * Fotos en las que sale un usuario, entre las que puede ver quien consulta.
     */
    public function tagged(int $userId): ResponseInterface
    {
        if (auth()->getProvider()->findById($userId) === null) {
            return $this->message('No se ha encontrado el usuario.', 404);
        }

        $limit = (int) $this->request->getGet('limit');
        $query = db_connect()->table('photo_tags')
            ->select('photos.*')
            ->join('photos', 'photos.id = photo_tags.photo_id')
            ->join('albums', 'albums.id = photos.album_id')
            ->where('photo_tags.user_id', $userId)
            ->whereIn('albums.user_id', $this->photoOwnersVisibleTo((int) auth()->user()->id));
        $total  = $query->countAllResults(false);
        $photos = $query->orderBy('photo_tags.id', 'DESC')->limit($limit >= 1 ? min($limit, 50) : 50)->get()->getResultArray();

        return $this->respond(['photos' => array_map($this->photoData(...), $photos), 'total' => $total]);
    }

    /**
     * Foto con el id y el título de su álbum y el id de su dueño.
     *
     * @return array<string, mixed>|null
     */
    private function photo(int $id): ?array
    {
        return db_connect()->table('photos')
            ->select('photos.*, albums.user_id AS owner_id, albums.title AS album_title')
            ->join('albums', 'albums.id = photos.album_id')
            ->where('photos.id', $id)
            ->get()
            ->getRowArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewablePhoto(int $id): ?array
    {
        $photo = $this->photo($id);

        return $photo !== null && $this->canSeePhotosOf((int) $photo['owner_id']) ? $photo : null;
    }

    /**
     * Uno puede etiquetarse a sí mismo o a un amigo suyo que pueda ver la foto.
     */
    private function canTag(int $taggerId, int $userId, int $ownerId): bool
    {
        if ($userId === $taggerId) {
            return true;
        }

        return model(FriendshipModel::class)->areFriends($taggerId, $userId) && $this->canSeePhotosOf($ownerId, $userId);
    }

    /**
     * @param array<string, mixed> $tag
     * @param array<string, mixed> $photo
     */
    private function canRemoveTag(array $tag, array $photo): bool
    {
        $userId = (int) auth()->user()->id;

        return in_array($userId, [(int) $tag['user_id'], (int) $tag['tagger_id'], (int) $photo['owner_id']], true);
    }

    /**
     * @param array<string, mixed> $photo
     *
     * @return list<array<string, mixed>>
     */
    private function tagList(array $photo): array
    {
        $rows = db_connect()->table('photo_tags')
            ->select('photo_tags.id AS tag_id, photo_tags.tagger_id, photo_tags.user_id AS tagged_id, photo_tags.x, photo_tags.y, photo_tags.width, photo_tags.height, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = photo_tags.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('photo_tags.photo_id', (int) $photo['id'])
            ->orderBy('photo_tags.x')
            ->get()
            ->getResultArray();

        $viewerId = (int) auth()->user()->id;
        $friends  = $this->photoOwnersVisibleTo($viewerId);

        return array_map(fn (array $row): array => [
            'id'         => (int) $row['tag_id'],
            'user'       => $this->personData($row, in_array((int) $row['id'], $friends, true)),
            'x'          => (float) $row['x'],
            'y'          => (float) $row['y'],
            'width'      => (float) $row['width'],
            'height'     => (float) $row['height'],
            'can_remove' => $this->canRemoveTag(['user_id' => $row['tagged_id'], 'tagger_id' => $row['tagger_id']], $photo),
        ], $rows);
    }

    /**
     * @param array<string, mixed> $photo
     *
     * @return list<array<string, mixed>>
     */
    private function commentList(array $photo): array
    {
        $rows = db_connect()->table('photo_comments')
            ->select('photo_comments.id AS comment_id, photo_comments.body, photo_comments.created_at, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = photo_comments.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('photo_comments.photo_id', (int) $photo['id'])
            ->orderBy('photo_comments.id')
            ->get()
            ->getResultArray();

        $viewerId = (int) auth()->user()->id;
        $friends  = $this->photoOwnersVisibleTo($viewerId);
        $isOwner  = $viewerId === (int) $photo['owner_id'];

        return array_map(fn (array $row): array => [
            'id'         => (int) $row['comment_id'],
            'author'     => $this->personData($row, in_array((int) $row['id'], $friends, true)),
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
            'can_delete' => $isOwner || (int) $row['id'] === $viewerId,
        ], $rows);
    }
}
