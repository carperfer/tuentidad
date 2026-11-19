<?php

namespace App\Controllers\Api;

use App\Models\VideoModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Vídeos de YouTube compartidos. Como las fotos, solo los ven su dueño y sus amigos.
 * La SPA no carga nada de YouTube hasta que se pulsa para reproducir.
 */
class Videos extends ApiController
{
    private const PAGE = 10;

    /**
     * Últimos vídeos de tus amigos y tuyos (página «Vídeos»), de 10 en 10 con ?before=<id>.
     */
    public function feed(): ResponseInterface
    {
        return $this->page($this->photoOwnersVisibleTo((int) auth()->user()->id));
    }

    public function ofUser(int $userId): ResponseInterface
    {
        if (auth()->getProvider()->findById($userId) === null) {
            return $this->message('No se ha encontrado el usuario.', 404);
        }
        if (! $this->canSeePhotosOf($userId)) {
            return $this->message('Solo sus amigos pueden ver sus vídeos.', 403);
        }

        return $this->page([$userId]);
    }

    public function create(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (($limit = $this->throttle("video-{$userId}", 30, HOUR)) !== null) {
            return $limit;
        }

        $input     = $this->input();
        $url       = is_string($input['url'] ?? null) ? $input['url'] : '';
        $title     = is_string($input['title'] ?? null) ? trim($input['title']) : '';
        $youtubeId = $url === '' ? null : VideoModel::youtubeId($url);

        $errors = [];
        if ($youtubeId === null) {
            $errors['url'] = 'Pega el enlace de un vídeo de YouTube.';
        }
        if ($title === '') {
            $errors['title'] = 'Ponle un título al vídeo.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'El título no puede tener más de 150 caracteres.';
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $videos = model(VideoModel::class);
        $id     = $videos->insert(['user_id' => $userId, 'youtube_id' => $youtubeId, 'title' => $title]);
        if ($id === false) {
            return $this->message('No se ha podido guardar el vídeo.', 500);
        }

        return $this->respondCreated(['video' => $this->videoData($this->rows([$userId])->where('videos.id', $id)->get()->getRowArray())]);
    }

    public function delete(int $id): ResponseInterface
    {
        $db = db_connect();
        $db->table('videos')->where('id', $id)->where('user_id', (int) auth()->user()->id)->delete();

        return $db->affectedRows() > 0 ? $this->message('Vídeo borrado.') : $this->message('No se ha encontrado el vídeo.', 404);
    }

    /**
     * @param list<int> $ownerIds
     */
    private function page(array $ownerIds): ResponseInterface
    {
        $before = (int) $this->request->getGet('before');
        $query  = $this->rows($ownerIds)->orderBy('videos.id', 'DESC')->limit(self::PAGE + 1);
        if ($before > 0) {
            $query->where('videos.id <', $before);
        }
        $total = $before > 0 ? null : db_connect()->table('videos')->whereIn('user_id', $ownerIds)->countAllResults();
        $rows  = $query->get()->getResultArray();
        $more  = count($rows) > self::PAGE;
        $items = array_map($this->videoData(...), array_slice($rows, 0, self::PAGE));

        return $this->respond([
            'videos'      => $items,
            'next_before' => $more ? end($items)['id'] : null,
            'total'       => $total,
        ]);
    }

    /**
     * @param list<int> $ownerIds
     */
    private function rows(array $ownerIds): BaseBuilder
    {
        return db_connect()->table('videos')
            ->select('videos.id AS video_id, videos.youtube_id, videos.title, videos.created_at, videos.user_id, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = videos.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->whereIn('videos.user_id', $ownerIds);
    }

    /**
     * @param array<string, mixed> $row Fila de rows()
     *
     * @return array<string, mixed>
     */
    private function videoData(array $row): array
    {
        return [
            'id'         => (int) $row['video_id'],
            'youtube_id' => $row['youtube_id'],
            'title'      => $row['title'],
            'created_at' => $row['created_at'],
            // Quien lo ve es el dueño o un amigo suyo
            'owner'    => $this->personData($row, true),
            'is_owner' => (int) $row['user_id'] === (int) auth()->user()->id,
        ];
    }
}
