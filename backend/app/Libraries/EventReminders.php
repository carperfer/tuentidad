<?php

namespace App\Libraries;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;

/**
 * Recordatorios de los eventos que empiezan en las próximas 24 horas, para
 * quienes van o quizás van: un aviso en la web y un email. Cada evento se
 * recuerda una sola vez (reminded_at), salvo que cambie de fecha.
 */
class EventReminders
{
    /**
     * Zona horaria en la que se escriben las fechas de los emails.
     */
    private const TIMEZONE = 'Europe/Madrid';

    public function __construct(private readonly BaseConnection $db, private readonly Mailer $mailer)
    {
    }

    /**
     * @return array{events: int, reminders: int, emails_failed: int}
     */
    public function send(): array
    {
        $now    = date('Y-m-d H:i:s');
        $events = $this->db->table('events')
            ->where('reminded_at', null)
            ->where('starts_at >=', $now)
            ->where('starts_at <', date('Y-m-d H:i:s', strtotime('+24 hours')))
            ->get()
            ->getResultArray();

        $reminders = 0;
        $failed    = 0;

        foreach ($events as $event) {
            // Se marca antes de enviar: si algo falla, no se repite cada hora
            $this->db->table('events')->where('id', $event['id'])->update(['reminded_at' => $now]);

            $attendees = $this->db->table('event_invitations')
                ->select('users.id, users.first_name, auth_identities.secret AS email')
                ->join('users', 'users.id = event_invitations.user_id')
                ->join('auth_identities', "auth_identities.user_id = users.id AND auth_identities.type = 'email_password'")
                ->where('event_invitations.event_id', $event['id'])
                ->whereIn('event_invitations.status', ['yes', 'maybe'])
                ->get()
                ->getResultArray();

            foreach ($attendees as $attendee) {
                model(NotificationModel::class)->notify((int) $attendee['id'], (int) $event['user_id'], 'event_reminder', ['event_id' => (int) $event['id']]);
                $reminders++;

                $sent = $this->mailer->send($attendee['email'], "Recordatorio: {$event['title']}", 'emails/event_reminder', [
                    'firstName' => $attendee['first_name'],
                    'title'     => $event['title'],
                    'when'      => $this->when($event['starts_at']),
                    'place'     => $event['place'],
                    'url'       => config('Tuentidad')->url("eventos/{$event['id']}"),
                ]);
                if (! $sent) {
                    $failed++;
                }
            }
        }

        return ['events' => count($events), 'reminders' => $reminders, 'emails_failed' => $failed];
    }

    /**
     * "el sábado 21 de noviembre a las 18:30" en hora de España.
     */
    private function when(string $startsAt): string
    {
        $date      = new DateTimeImmutable($startsAt, new DateTimeZone('UTC'));
        $formatter = new IntlDateFormatter('es_ES', IntlDateFormatter::NONE, IntlDateFormatter::NONE, self::TIMEZONE, null, "EEEE d 'de' MMMM 'a las' HH:mm");

        return 'el ' . $formatter->format($date);
    }
}
