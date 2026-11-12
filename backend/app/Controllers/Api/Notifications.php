<?php

namespace App\Controllers\Api;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Avisos del usuario autenticado. Los de una foto solo cuentan mientras
 * el usuario puede verla.
 */
class Notifications extends ApiController
{
    private const LIST_LIMIT = 30;

    public function index(): ResponseInterface
    {
        $rows = $this->visible()
            ->select('notifications.id AS notification_id, notifications.type, notifications.photo_id, notifications.created_at, notifications.read_at, photos.album_id, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = notifications.actor_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->orderBy('notifications.id', 'DESC')
            ->limit(self::LIST_LIMIT)
            ->get()
            ->getResultArray();

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
            'created_at' => $row['created_at'],
            'read'       => $row['read_at'] !== null,
        ], $rows);

        return $this->respond(['notifications' => $notifications, 'unread' => $this->unreadCounts()]);
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
     * Avisos del usuario sobre nada o sobre fotos que puede ver.
     */
    private function visible(): BaseBuilder
    {
        $userId = (int) auth()->user()->id;

        return db_connect()->table('notifications')
            ->join('photos', 'photos.id = notifications.photo_id', 'left')
            ->join('albums', 'albums.id = photos.album_id', 'left')
            ->where('notifications.user_id', $userId)
            ->groupStart()
            ->where('notifications.photo_id', null)
            ->orWhereIn('albums.user_id', $this->photoOwnersVisibleTo($userId))
            ->groupEnd();
    }
}
