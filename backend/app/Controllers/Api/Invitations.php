<?php

namespace App\Controllers\Api;

use App\Libraries\Mailer;
use App\Libraries\Tokens;
use App\Models\InvitationModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Invitaciones: enviar, listar las propias y consultar una por su token.
 */
class Invitations extends ApiController
{
    private const INVALID = 'La invitación no existe, ya se ha usado o ha caducado.';

    public function index(): ResponseInterface
    {
        $user  = auth()->user();
        $model = model(InvitationModel::class);
        $now   = date('Y-m-d H:i:s');

        $invitations = array_map(static fn (array $row): array => [
            'email'      => $row['email'],
            'status'     => $row['accepted_at'] !== null ? 'accepted' : ($row['expires_at'] <= $now ? 'expired' : 'pending'),
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
        ], $model->listSentBy((int) $user->id));

        return $this->respond([
            'remaining'   => $this->remaining((int) $user->id),
            'invitations' => $invitations,
        ]);
    }

    public function create(): ResponseInterface
    {
        $user = auth()->user();

        if (($limit = $this->throttle("invite-{$user->id}", 20, HOUR)) !== null) {
            return $limit;
        }

        $email = $this->normalizeEmail($this->input()['email'] ?? '');
        if (! $this->validateData(['email' => $email], ['email' => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[254]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        if (auth()->getProvider()->findByCredentials(['email' => $email]) !== null) {
            return $this->invalid(['email' => 'Esta persona ya está en tuentidad.']);
        }

        $model    = model(InvitationModel::class);
        $config   = config('Tuentidad');
        $token    = Tokens::generate();
        $expires  = date('Y-m-d H:i:s', time() + $config->invitationLifetime);
        $existing = $model->findPending((int) $user->id, $email);

        if ($existing !== null) {
            // Reenvío: nuevo enlace y nueva caducidad, sin gastar otra invitación
            $model->update($existing['id'], ['token_hash' => $token['hash'], 'expires_at' => $expires]);
            $id = $existing['id'];
        } else {
            if ($this->remaining((int) $user->id) <= 0) {
                return $this->invalid(['email' => 'Ya has usado todas tus invitaciones.']);
            }
            $id = $model->insert([
                'inviter_id' => $user->id,
                'email'      => $email,
                'token_hash' => $token['hash'],
                'expires_at' => $expires,
            ]);
        }

        $sent = (new Mailer())->send(
            $email,
            "{$user->first_name} te invita a tuentidad",
            'emails/invitation',
            [
                'inviterName' => trim("{$user->first_name} {$user->last_name}"),
                'url'         => $config->url('registro/' . $token['token']),
                'days'        => intdiv($config->invitationLifetime, DAY),
            ],
        );

        if (! $sent) {
            if ($existing === null) {
                $model->delete($id);
            }

            return $this->message('No se ha podido enviar la invitación. Inténtalo más tarde.', 502);
        }

        return $this->respond([
            'message'   => $existing !== null ? "Hemos vuelto a enviar la invitación a {$email}." : "Invitación enviada a {$email}.",
            'remaining' => $this->remaining((int) $user->id),
        ], $existing !== null ? 200 : 201);
    }

    /**
     * Datos públicos de una invitación válida, para el formulario de registro.
     */
    public function show(string $token): ResponseInterface
    {
        if (($limit = $this->throttle('invitation-' . $this->request->getIPAddress(), 30, MINUTE)) !== null) {
            return $limit;
        }

        $invitation = Tokens::isWellFormed($token) ? model(InvitationModel::class)->findUsableByToken($token) : null;
        if ($invitation === null) {
            return $this->message(self::INVALID, 404);
        }

        $inviter = $invitation['inviter_id'] !== null ? auth()->getProvider()->findById($invitation['inviter_id']) : null;

        return $this->respond([
            'email'   => $invitation['email'],
            'inviter' => $inviter !== null ? trim("{$inviter->first_name} {$inviter->last_name}") : null,
        ]);
    }

    private function remaining(int $userId): int
    {
        return max(0, config('Tuentidad')->invitationQuota - model(InvitationModel::class)->countSentBy($userId));
    }
}
