<?php

namespace App\Controllers\Api;

use App\Libraries\Tokens;
use App\Models\FriendshipModel;
use App\Models\InvitationModel;
use App\Models\WelcomeProfileModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Registro de una cuenta nueva a partir de una invitación.
 */
class Registration extends ApiController
{
    public function register(): ResponseInterface
    {
        if (($limit = $this->throttle('register-' . $this->request->getIPAddress(), 10, 15 * MINUTE)) !== null) {
            return $limit;
        }

        $input      = $this->input();
        $token      = is_string($input['token'] ?? null) ? $input['token'] : '';
        $invitation = Tokens::isWellFormed($token) ? model(InvitationModel::class)->findUsableByToken($token) : null;

        if ($invitation === null) {
            return $this->message('La invitación no existe, ya se ha usado o ha caducado.', 404);
        }

        $data = [
            'email'            => $invitation['email'],
            'first_name'       => is_string($input['first_name'] ?? null) ? trim($input['first_name']) : '',
            'last_name'        => is_string($input['last_name'] ?? null) ? trim($input['last_name']) : '',
            'birthdate'        => $input['birthdate'] ?? '',
            'password'         => $input['password'] ?? '',
            'password_confirm' => $input['password_confirm'] ?? '',
            'accept_terms'     => empty($input['accept_terms']) ? '' : '1',
        ];

        $valid = $this->validateData($data, [
            'first_name'       => ['label' => 'Nombre', 'rules' => 'required|max_length[50]'],
            'last_name'        => ['label' => 'Apellidos', 'rules' => 'required|max_length[80]'],
            'birthdate'        => ['label' => 'Fecha de nacimiento', 'rules' => 'required|valid_birthdate'],
            'password'         => ['label' => 'Contraseña', 'rules' => 'required|string|max_length[255]|strong_password[]'],
            'password_confirm' => ['label' => 'Repite la contraseña', 'rules' => 'required|matches[password]'],
            'accept_terms'     => [
                'label'  => 'Condiciones',
                'rules'  => 'required',
                'errors' => ['required' => 'Tienes que aceptar las condiciones de uso y la política de privacidad.'],
            ],
        ]);
        if (! $valid) {
            return $this->invalid($this->validator->getErrors());
        }

        $users = auth()->getProvider();
        if ($users->findByCredentials(['email' => $data['email']]) !== null) {
            return $this->message('Ya existe una cuenta con este email. Inicia sesión.', 409);
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $user = new User([
                'email'      => $data['email'],
                'password'   => $data['password'],
                'first_name' => $data['first_name'],
                'last_name'  => $data['last_name'],
                'birthdate'  => $data['birthdate'],
            ]);
            $users->save($user);
            $user = $users->findById($users->getInsertID());
            $users->addToDefaultGroup($user);
            $user->activate();

            // Solo una petición puede aceptar la invitación, aunque lleguen dos a la vez
            $db->table('invitations')
                ->where('id', $invitation['id'])
                ->where('accepted_at', null)
                ->update(['accepted_at' => date('Y-m-d H:i:s'), 'user_id' => $user->id]);

            if ($db->affectedRows() !== 1) {
                $db->transRollback();

                return $this->message('La invitación no existe, ya se ha usado o ha caducado.', 404);
            }

            // Quien entra pidiendo amistad a un perfil de bienvenida, queda como su amigo
            $inviterId = (int) $invitation['inviter_id'];
            if ($inviterId > 0 && model(WelcomeProfileModel::class)->isWelcomeUser($inviterId)) {
                model(FriendshipModel::class)->befriend((int) $user->id, $inviterId);
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        auth('session')->login($user);

        return $this->respond(['user' => $this->userData($user)], 201);
    }
}
