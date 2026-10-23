<?php

namespace App\Controllers\Api;

use App\Libraries\InvitationSender;
use App\Libraries\Tokens;
use App\Models\InvitationModel;
use App\Models\WelcomeProfileModel;
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

        $isPending = model(InvitationModel::class)->findPending((int) $user->id, $email) !== null;
        if (! $isPending && $this->remaining((int) $user->id) <= 0) {
            return $this->invalid(['email' => 'Ya has usado todas tus invitaciones.']);
        }

        $result = (new InvitationSender())->send(
            (int) $user->id,
            $email,
            "{$user->first_name} te invita a tuentidad",
            'emails/invitation',
            ['inviterName' => trim("{$user->first_name} {$user->last_name}")],
        );

        if ($result === InvitationSender::FAILED) {
            return $this->message('No se ha podido enviar la invitación. Inténtalo más tarde.', 502);
        }

        $resent = $result === InvitationSender::RESENT;

        return $this->respond([
            'message'   => $resent ? "Hemos vuelto a enviar la invitación a {$email}." : "Invitación enviada a {$email}.",
            'remaining' => $this->remaining((int) $user->id),
        ], $resent ? 200 : 201);
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
            // Al registrarse, quedará como amigo de este perfil de bienvenida
            'welcome' => $inviter !== null && model(WelcomeProfileModel::class)->isWelcomeUser((int) $inviter->id),
        ]);
    }

    private function remaining(int $userId): int
    {
        return max(0, config('Tuentidad')->invitationQuota - model(InvitationModel::class)->countSentBy($userId));
    }
}
