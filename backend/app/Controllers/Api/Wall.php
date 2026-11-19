<?php

namespace App\Controllers\Api;

use App\Models\NotificationModel;
use App\Models\WallPostModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tablón del perfil: lo leen y escriben el dueño y sus amigos. Borran un
 * mensaje su autor y el dueño del tablón.
 */
class Wall extends ApiController
{
    private const PAGE = 10;

    /**
     * Mensajes del más reciente al más antiguo, de 10 en 10 (?before=<id>).
     */
    public function index(int $profileId): ResponseInterface
    {
        if (($denied = $this->denied($profileId)) !== null) {
            return $denied;
        }

        $before = (int) $this->request->getGet('before');
        $query  = db_connect()->table('wall_posts')
            ->select('wall_posts.id AS post_id, wall_posts.body, wall_posts.created_at, wall_posts.author_id, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = wall_posts.author_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('wall_posts.profile_id', $profileId)
            ->orderBy('wall_posts.id', 'DESC')
            ->limit(self::PAGE + 1);
        if ($before > 0) {
            $query->where('wall_posts.id <', $before);
        }
        $rows  = $query->get()->getResultArray();
        $more  = count($rows) > self::PAGE;
        $posts = array_map(fn (array $row): array => $this->postData($row, $profileId), array_slice($rows, 0, self::PAGE));

        return $this->respond(['posts' => $posts, 'next_before' => $more ? end($posts)['id'] : null]);
    }

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

        $posts = model(WallPostModel::class);
        $id    = (int) $posts->insert(['profile_id' => $profileId, 'author_id' => $authorId, ...$data]);
        model(NotificationModel::class)->notify($profileId, $authorId, 'wall_post', ['wall_post_id' => $id]);

        $row = db_connect()->table('wall_posts')
            ->select('wall_posts.id AS post_id, wall_posts.body, wall_posts.created_at, wall_posts.author_id, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = wall_posts.author_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('wall_posts.id', $id)
            ->get()
            ->getRowArray();

        return $this->respondCreated(['post' => $this->postData($row, $profileId)]);
    }

    public function delete(int $id): ResponseInterface
    {
        $post   = model(WallPostModel::class)->find($id);
        $userId = (int) auth()->user()->id;
        if ($post === null || ! in_array($userId, [(int) $post['author_id'], (int) $post['profile_id']], true)) {
            return $this->message('No se ha encontrado el mensaje.', 404);
        }

        model(WallPostModel::class)->delete($id);

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

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function postData(array $row, int $profileId): array
    {
        $userId = (int) auth()->user()->id;

        return [
            'id'         => (int) $row['post_id'],
            'author'     => $this->personData($row, true),
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
            'can_delete' => in_array($userId, [(int) $row['author_id'], $profileId], true),
        ];
    }
}
