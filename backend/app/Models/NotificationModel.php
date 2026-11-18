<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Avisos en la web: etiquetas, comentarios en fotos, solicitudes de amistad y eventos.
 */
class NotificationModel extends Model
{
    public const TYPES = ['photo_tag', 'photo_comment', 'friend_request', 'event_invite', 'event_comment', 'event_reminder'];

    protected $table         = 'notifications';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['user_id', 'actor_id', 'type', 'photo_id', 'photo_tag_id', 'photo_comment_id', 'friend_request_id', 'event_id', 'event_comment_id', 'read_at'];
    protected $dateFormat    = 'datetime';

    /**
     * Avisa a un usuario de algo que ha hecho otro (nunca de lo que hace él mismo).
     *
     * @param array<string, int> $refs photo_id, photo_tag_id, photo_comment_id, friend_request_id, event_id o event_comment_id
     */
    public function notify(int $userId, int $actorId, string $type, array $refs = []): void
    {
        if ($userId === $actorId) {
            return;
        }

        $this->insert(['user_id' => $userId, 'actor_id' => $actorId, 'type' => $type, ...$refs]);
    }
}
