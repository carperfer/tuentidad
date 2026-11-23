<?php

namespace App\Controllers\Api;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Avisos del usuario autenticado. Los de una foto solo cuentan mientras
 * el usuario puede verla, y los de un evento mientras siga invitado.
 */
class Notifications extends ApiController
{
    private const PAGE = 20;

    /**
     * Avisos del más reciente al más antiguo, de 20 en 20.
     * ?group=photos|friends|events filtra; ?before=<id> trae la página siguiente.
     */
    public function index(): ResponseInterface
    {
        $group  = (string) $this->request->getGet('group');
        $before = (int) $this->request->getGet('before');
        if ($group !== '' && ! isset(NotificationModel::GROUPS[$group])) {
            return $this->invalid(['group' => 'Filtro no válido.']);
        }

        $query = $this->visible()
            ->select('notifications.id AS notification_id, notifications.type, notifications.photo_id, notifications.event_id, notifications.created_at, notifications.read_at, photos.album_id, events.title AS event_title, wall_posts.profile_id AS wall_profile_id, ' . self::PERSON_COLUMNS)
            ->join('wall_posts', 'wall_posts.id = notifications.wall_post_id', 'left')
            ->join('users', 'users.id = notifications.actor_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->orderBy('notifications.id', 'DESC')
            // Uno de más para saber si hay otra página
            ->limit(self::PAGE + 1);
        if ($group !== '') {
            $query->whereIn('notifications.type', NotificationModel::GROUPS[$group]);
        }
        if ($before > 0) {
            $query->where('notifications.id <', $before);
        }
        $rows = $query->get()->getResultArray();
        $more = count($rows) > self::PAGE;
        $rows = array_slice($rows, 0, self::PAGE);

        $friends       = $this->friendIds((int) auth()->user()->id);
        $notifications = array_map(fn (array $row): array => [
            'id'         => (int) $row['notification_id'],
            'type'       => $row['type'],
            'actor'      => $this->personData($row, in_array((int) $row['id'], $friends, true)),
            'photo'      => $row['photo_id'] === null ? null : [
                'id'        => (int) $row['photo_id'],
                'album_id'  => (int) $row['album_id'],
                'thumb_url' => "/api/photos/{$row['photo_id']}/thumb",
            ],
            'event'      => $row['event_id'] === null ? null : ['id' => (int) $row['event_id'], 'title' => $row['event_title']],
            // Tablón en el que está el mensaje
            'wall'       => $row['wall_profile_id'] === null ? null : ['profile_id' => (int) $row['wall_profile_id']],
            'created_at' => $row['created_at'],
            'read'       => $row['read_at'] !== null,
        ], $rows);

        return $this->respond([
            'notifications' => $notifications,
            'next_before'   => $more ? end($notifications)['id'] : null,
            'unread'        => $this->unreadCounts(),
        ]);
    }

    /**
     * Marca un aviso como leído (al abrirlo desde la lista).
     */
    public function markOneRead(int $id): ResponseInterface
    {
        model(NotificationModel::class)
            ->where('id', $id)
            ->where('user_id', (int) auth()->user()->id)
            ->where('read_at', null)
            ->set('read_at', date('Y-m-d H:i:s'))
            ->update();

        return $this->respond(['unread' => $this->unreadCounts()]);
    }

    /**
     * Avisos sin leer por tipo (para la topbar y la tarjeta de Inicio).
     */
    public function unread(): ResponseInterface
    {
        return $this->respond(['unread' => $this->unreadCounts()]);
    }

    public function markRead(): ResponseInterface
    {
        model(NotificationModel::class)
            ->where('user_id', (int) auth()->user()->id)
            ->where('read_at', null)
            ->set('read_at', date('Y-m-d H:i:s'))
            ->update();

        return $this->respond(['unread' => $this->unreadCounts()]);
    }

    /**
     * @return array<string, int> total y uno por tipo
     */
    private function unreadCounts(): array
    {
        $rows = $this->visible()
            ->select('notifications.type, COUNT(*) AS total')
            ->where('notifications.read_at', null)
            ->groupBy('notifications.type')
            ->get()
            ->getResultArray();

        $counts = array_fill_keys(NotificationModel::TYPES, 0);

        foreach ($rows as $row) {
            $counts[$row['type']] = (int) $row['total'];
        }

        return ['total' => array_sum($counts), ...$counts];
    }

    /**
     * Avisos del usuario sobre fotos que puede ver, eventos a los que sigue
     * invitado (o que organiza) o sobre nada en concreto.
     */
    private function visible(): BaseBuilder
    {
        $userId = (int) auth()->user()->id;

        return db_connect()->table('notifications')
            ->join('photos', 'photos.id = notifications.photo_id', 'left')
            ->join('albums', 'albums.id = photos.album_id', 'left')
            ->join('events', 'events.id = notifications.event_id', 'left')
            ->where('notifications.user_id', $userId)
            ->groupStart()
            ->where('notifications.photo_id', null)
            ->orWhereIn('albums.user_id', $this->photoOwnersVisibleTo($userId))
            ->groupEnd()
            ->groupStart()
            ->where('notifications.event_id', null)
            ->orWhere('events.user_id', $userId)
            ->orWhereIn('notifications.event_id', db_connect()->table('event_invitations')->select('event_id')->where('user_id', $userId))
            ->groupEnd();
    }
}
