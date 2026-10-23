<?php

namespace App\Libraries;

use App\Models\InvitationModel;

/**
 * Crea (o renueva, si ya hay una pendiente) una invitación y envía el email.
 */
class InvitationSender
{
    public const CREATED = 'created';
    public const RESENT  = 'resent';
    public const FAILED  = 'failed';

    /**
     * @param array<string, mixed> $data   Datos de la vista del email; se añaden `url` y `days`
     * @param array<string, mixed> $fields Campos extra de la invitación (p. ej. consented_at)
     *
     * @return self::CREATED|self::FAILED|self::RESENT
     */
    public function send(int $inviterId, string $email, string $subject, string $view, array $data = [], array $fields = []): string
    {
        $model    = model(InvitationModel::class);
        $config   = config('Tuentidad');
        $token    = Tokens::generate();
        $expires  = date('Y-m-d H:i:s', time() + $config->invitationLifetime);
        $existing = $model->findPending($inviterId, $email);

        if ($existing !== null) {
            // Nuevo enlace y nueva caducidad: el anterior deja de valer
            $model->update($existing['id'], ['token_hash' => $token['hash'], 'expires_at' => $expires] + $fields);
            $id = $existing['id'];
        } else {
            $id = $model->insert([
                'inviter_id' => $inviterId,
                'email'      => $email,
                'token_hash' => $token['hash'],
                'expires_at' => $expires,
            ] + $fields);
        }

        $sent = (new Mailer())->send($email, $subject, $view, $data + [
            'url'  => $config->url('registro/' . $token['token']),
            'days' => intdiv($config->invitationLifetime, DAY),
        ]);

        if (! $sent) {
            if ($existing === null) {
                $model->delete($id);
            }

            return self::FAILED;
        }

        return $existing !== null ? self::RESENT : self::CREATED;
    }
}
