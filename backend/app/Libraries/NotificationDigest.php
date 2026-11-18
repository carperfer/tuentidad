<?php

namespace App\Libraries;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Emails de resumen para quien los ha activado (cada día o cada semana):
 * avisos sin leer y mensajes de chat sin leer desde el último resumen.
 * Si no hay nada nuevo, no se envía nada.
 */
class NotificationDigest
{
    private const MAX_ITEMS = 10;

    /**
     * Margen para que un workflow que llega unos minutos antes no se salte un día.
     */
    private const INTERVALS = ['daily' => 20 * HOUR, 'weekly' => 6 * DAY + 20 * HOUR];

    public function __construct(private readonly BaseConnection $db, private readonly Mailer $mailer)
    {
    }

    /**
     * @return array{users: int, sent: int, failed: int}
     */
    public function send(): array
    {
        $now   = time();
        $users = $this->db->table('users')
            ->select('users.id, users.first_name, users.digest, users.digest_sent_at, auth_identities.secret AS email')
            ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'")
            ->whereIn('users.digest', array_keys(self::INTERVALS))
            ->get()
            ->getResultArray();

        $sent   = 0;
        $failed = 0;

        foreach ($users as $user) {
            $last = $user['digest_sent_at'] === null ? 0 : (int) strtotime($user['digest_sent_at'] . ' UTC');
            if ($now - $last < self::INTERVALS[$user['digest']]) {
                continue;
            }

            $since = date('Y-m-d H:i:s', max($last, $now - 7 * DAY));
            $items = $this->items((int) $user['id'], $since);
            $chat  = $this->db->table('messages')->where('recipient_id', $user['id'])->where('read_at', null)->where('created_at >', $since)->countAllResults();
            if ($items['total'] === 0 && $chat === 0) {
                continue;
            }

            $ok = $this->mailer->send($user['email'], 'Novedades en tuentidad', 'emails/digest', [
                'firstName' => $user['first_name'],
                'items'     => $items['list'],
                'more'      => $items['total'] - count($items['list']),
                'messages'  => $chat,
                'url'       => config('Tuentidad')->url('avisos'),
                'settings'  => config('Tuentidad')->url('perfil/editar'),
            ]);
            if ($ok) {
                $this->db->table('users')->where('id', $user['id'])->update(['digest_sent_at' => date('Y-m-d H:i:s', $now)]);
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['users' => count($users), 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Avisos sin leer desde una fecha: los más recientes como frases y el total.
     *
     * @return array{list: list<string>, total: int}
     */
    private function items(int $userId, string $since): array
    {
        $query = $this->db->table('notifications')
            ->select("notifications.type, CONCAT(users.first_name, ' ', users.last_name) AS actor, events.title AS event", false)
            ->join('users', 'users.id = notifications.actor_id')
            ->join('events', 'events.id = notifications.event_id', 'left')
            ->where('notifications.user_id', $userId)
            ->where('notifications.read_at', null)
            ->where('notifications.created_at >', $since);
        $total = $query->countAllResults(false);
        $rows  = $query->orderBy('notifications.id', 'DESC')->limit(self::MAX_ITEMS)->get()->getResultArray();

        $list = array_map(static function (array $row): string {
            $text = $row['actor'] . ' ' . (NotificationModel::TEXT[$row['type']] ?? 'tiene algo para ti');

            return $row['event'] !== null ? "{$text}: «{$row['event']}»" : $text;
        }, $rows);

        return ['list' => $list, 'total' => $total];
    }
}
