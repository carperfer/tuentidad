<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Avisos en la web: etiquetas, comentarios en fotos, amistades y eventos.
 */
class NotificationModel extends Model
{
    public const TYPES = [
        'photo_tag', 'photo_comment',
        'friend_request', 'friend_accepted', 'invitation_accepted',
        'event_invite', 'event_comment', 'event_reminder',
    ];

    /**
     * Grupos de la página de avisos.
     */
    public const GROUPS = [
        'photos'  => ['photo_tag', 'photo_comment'],
        'friends' => ['friend_request', 'friend_accepted', 'invitation_accepted'],
        'events'  => ['event_invite', 'event_comment', 'event_reminder'],
    ];

    /**
     * Qué ha hecho quien genera el aviso, para los emails de resumen
     * (la SPA tiene sus propios textos).
     */
    public const TEXT = [
        'photo_tag'           => 'te ha etiquetado en una foto',
        'photo_comment'       => 'ha comentado una foto',
        'friend_request'      => 'te ha enviado una solicitud de amistad',
        'friend_accepted'     => 'ha aceptado tu solicitud de amistad',
        'invitation_accepted' => 'ha aceptado tu invitación y ya sois amigos',
        'event_invite'        => 'te ha invitado a un evento',
        'event_comment'       => 'ha comentado en un evento',
        'event_reminder'      => 'te recuerda un evento de mañana',
    ];

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
