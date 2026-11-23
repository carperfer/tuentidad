<?php

namespace App\Controllers\Api;

use App\Libraries\LinkPreviewer;
use App\Models\LinkPreviewModel;
use App\Models\NotificationModel;
use App\Models\WallPostModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tablón del perfil: lo leen y escriben el dueño y sus amigos. Cada mensaje
 * puede tener un hilo de respuestas (de un nivel) y la tarjeta del primer
 * enlace que incluya. Borran un mensaje su autor y el dueño del tablón; al
 * borrar un mensaje principal se borran sus respuestas.
 */
class Wall extends ApiController
{
    private const PAGE = 10;

    /**
     * Mensajes principales del más reciente al más antiguo, de 10 en 10
     * (?before=<id>), cada uno con todas sus respuestas en orden.
     */
    public function index(int $profileId): ResponseInterface
    {
        if (($denied = $this->denied($profileId)) !== null) {
            return $denied;
        }

        $before = (int) $this->request->getGet('before');
        $query  = $this->posts()
            ->where('wall_posts.profile_id', $profileId)
            ->where('wall_posts.parent_id', null)
            ->orderBy('wall_posts.id', 'DESC')
            ->limit(self::PAGE + 1);
        if ($before > 0) {
            $query->where('wall_posts.id <', $before);
        }
        $rows = $query->get()->getResultArray();
        $more = count($rows) > self::PAGE;
        $rows = array_slice($rows, 0, self::PAGE);

        $replies = [];
        if ($rows !== []) {
            $replyRows = $this->posts()
                ->whereIn('wall_posts.parent_id', array_column($rows, 'post_id'))
                ->orderBy('wall_posts.id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($replyRows as $reply) {
                $replies[(int) $reply['parent_id']][] = $this->postData($reply, $profileId);
            }
        }

        $posts = array_map(fn (array $row): array => [
            ...$this->postData($row, $profileId),
            'replies' => $replies[(int) $row['post_id']] ?? [],
        ], $rows);

        return $this->respond(['posts' => $posts, 'next_before' => $more ? end($posts)['id'] : null]);
    }

    /**
     * Escribe un mensaje o, con parent_id, una respuesta. Con link_preview
     * a false no se añade la tarjeta del enlace.
     */
    public function create(int $profileId): ResponseInterface
    {
        if (($denied = $this->denied($profileId)) !== null) {
            return $denied;
        }

        $authorId = (int) auth()->user()->id;
        if (($limit = $this->throttle("wall-{$authorId}", 60, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $data  = ['body' => is_string($input['body'] ?? null) ? trim($input['body']) : ''];
        if (! $this->validateData($data, ['body' => ['label' => 'Mensaje', 'rules' => 'required|max_length[1000]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $posts  = model(WallPostModel::class);
        $parent = null;
        if (isset($input['parent_id']) && $input['parent_id'] !== null) {
            $parent = is_int($input['parent_id']) ? $posts->find($input['parent_id']) : null;
            if ($parent === null || (int) $parent['profile_id'] !== $profileId) {
                return $this->message('No se ha encontrado el mensaje al que respondes.', 404);
            }
            // Responder a una respuesta sigue el mismo hilo
            if ($parent['parent_id'] !== null) {
                $parent = $posts->find($parent['parent_id']);
            }
        }

        $preview = null;
        $url     = ($input['link_preview'] ?? true) === false ? null : LinkPreviewer::firstUrl($data['body']);
        if ($url !== null) {
            $preview = model(LinkPreviewModel::class)->forUrl($url);
        }

        $id = (int) $posts->insert([
            'profile_id'      => $profileId,
            'author_id'       => $authorId,
            'parent_id'       => $parent === null ? null : (int) $parent['id'],
            'link_preview_id' => $preview === null ? null : (int) $preview['id'],
            ...$data,
        ]);

        $notifications = model(NotificationModel::class);
        if ($parent === null) {
            $notifications->notify($profileId, $authorId, 'wall_post', ['wall_post_id' => $id]);
        } else {
            $notifications->notify((int) $parent['author_id'], $authorId, 'wall_reply', ['wall_post_id' => $id]);
            if ((int) $parent['author_id'] !== $profileId) {
                $notifications->notify($profileId, $authorId, 'wall_post', ['wall_post_id' => $id]);
            }
        }

        $row  = $this->posts()->where('wall_posts.id', $id)->get()->getRowArray();
        $post = $this->postData($row, $profileId);
        if ($parent === null) {
            $post['replies'] = [];
        }

        return $this->respondCreated(['post' => $post]);
    }

    public function delete(int $id): ResponseInterface
    {
        $post   = model(WallPostModel::class)->find($id);
        $userId = (int) auth()->user()->id;
        if ($post === null || ! in_array($userId, [(int) $post['author_id'], (int) $post['profile_id']], true)) {
            return $this->message('No se ha encontrado el mensaje.', 404);
        }

        model(WallPostModel::class)->delete($id);
        model(LinkPreviewModel::class)->deleteUnused();

        return $this->message('Mensaje borrado.');
    }

    /**
     * 404 si el perfil no existe y 403 si no es tuyo ni de un amigo.
     */
    private function denied(int $profileId): ?ResponseInterface
    {
        if (auth()->getProvider()->findById($profileId) === null) {
            return $this->message('No se ha encontrado el usuario.', 404);
        }
        if (! $this->canSeePhotosOf($profileId)) {
            return $this->message('Solo sus amigos pueden ver y escribir en su tablón.', 403);
        }

        return null;
    }

    private function posts(): BaseBuilder
    {
        return db_connect()->table('wall_posts')
            ->select('wall_posts.id AS post_id, wall_posts.parent_id, wall_posts.body, wall_posts.created_at, wall_posts.author_id, '
                . 'link_previews.id AS preview_id, link_previews.url AS preview_url, link_previews.title AS preview_title, '
                . 'link_previews.description AS preview_description, link_previews.site_name AS preview_site_name, link_previews.image AS preview_image, '
                . self::PERSON_COLUMNS)
            ->join('users', 'users.id = wall_posts.author_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->join('link_previews', 'link_previews.id = wall_posts.link_preview_id', 'left');
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function postData(array $row, int $profileId): array
    {
        $userId = (int) auth()->user()->id;

        return [
            'id'           => (int) $row['post_id'],
            'parent_id'    => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'author'       => $this->personData($row, true),
            'body'         => $row['body'],
            'link_preview' => $row['preview_id'] === null ? null : LinkPreviewModel::data([
                'id'          => $row['preview_id'],
                'url'         => $row['preview_url'],
                'title'       => $row['preview_title'],
                'description' => $row['preview_description'],
                'site_name'   => $row['preview_site_name'],
                'image'       => $row['preview_image'],
            ]),
            'created_at' => $row['created_at'],
            'can_delete' => in_array($userId, [(int) $row['author_id'], $profileId], true),
        ];
    }
}
