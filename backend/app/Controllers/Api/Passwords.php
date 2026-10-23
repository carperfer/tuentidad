<?php

namespace App\Controllers\Api;

use App\Libraries\Mailer;
use App\Libraries\Tokens;
use App\Models\PasswordResetModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\RememberModel;

/**
 * Recuperación de contraseña por email.
 */
class Passwords extends ApiController
{
    public function forgot(): ResponseInterface
    {
        if (($limit = $this->throttle('forgot-' . $this->request->getIPAddress(), 5, 15 * MINUTE)) !== null) {
            return $limit;
        }

        $email = $this->normalizeEmail($this->input()['email'] ?? '');
        if (! $this->validateData(['email' => $email], ['email' => ['label' => 'Email', 'rules' => 'required|valid_email']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $user = auth()->getProvider()->findByCredentials(['email' => $email]);

        if ($user !== null && $user->isActivated()) {
            $config = config('Tuentidad');
            $model  = model(PasswordResetModel::class);
            $token  = Tokens::generate();

            $model->invalidateFor((int) $user->id);
            $model->insert([
                'user_id'    => $user->id,
                'token_hash' => $token['hash'],
                'expires_at' => date('Y-m-d H:i:s', time() + $config->passwordResetLifetime),
            ]);

            (new Mailer())->send($email, 'Cambia tu contraseña de tuentidad', 'emails/password_reset', [
                'firstName' => $user->first_name,
                'url'       => $config->url('restablecer-contrasena/' . $token['token']),
                'minutes'   => intdiv($config->passwordResetLifetime, MINUTE),
            ]);
        }

        // Misma respuesta exista o no la cuenta, para no revelar qué emails están registrados
        return $this->message('Si el email corresponde a una cuenta, recibirás un enlace para cambiar la contraseña.');
    }

    public function reset(): ResponseInterface
    {
        if (($limit = $this->throttle('reset-' . $this->request->getIPAddress(), 10, 15 * MINUTE)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $token = is_string($input['token'] ?? null) ? $input['token'] : '';
        $model = model(PasswordResetModel::class);
        $reset = Tokens::isWellFormed($token) ? $model->findUsableByToken($token) : null;
        $users = auth()->getProvider();
        $user  = $reset !== null ? $users->findById($reset['user_id']) : null;

        if ($user === null) {
            return $this->message('El enlace no es válido o ha caducado. Pide uno nuevo.', 404);
        }

        $data = [
            'email'            => $user->email,
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'password'         => $input['password'] ?? '',
            'password_confirm' => $input['password_confirm'] ?? '',
        ];
        $valid = $this->validateData($data, [
            'password'         => ['label' => 'Contraseña', 'rules' => 'required|string|max_length[255]|strong_password[]'],
            'password_confirm' => ['label' => 'Repite la contraseña', 'rules' => 'required|matches[password]'],
        ]);
        if (! $valid) {
            return $this->invalid($this->validator->getErrors());
        }

        $user->password = $data['password'];
        $users->save($user);

        $model->invalidateFor((int) $user->id);
        model(RememberModel::class)->purgeRememberTokens($user);

        return $this->message('Contraseña cambiada. Ya puedes iniciar sesión.');
    }
}
