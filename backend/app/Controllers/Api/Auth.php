<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sesión: usuario actual, inicio y cierre de sesión.
 */
class Auth extends ApiController
{
    public function me(): ResponseInterface
    {
        $user = auth('session')->user();

        return $this->respond(['user' => $user !== null ? $this->userData($user) : null]);
    }

    public function login(): ResponseInterface
    {
        $data  = $this->input();
        $email = $this->normalizeEmail($data['email'] ?? '');
        $ip    = $this->request->getIPAddress();

        if (($limit = $this->throttle("login-ip-{$ip}", 20, MINUTE)) !== null
            || ($limit = $this->throttle("login-email-{$email}", 5, 5 * MINUTE)) !== null) {
            return $limit;
        }

        $valid = $this->validateData(['email' => $email, 'password' => $data['password'] ?? null], [
            'email'    => ['label' => 'Email', 'rules' => 'required|valid_email'],
            'password' => ['label' => 'Contraseña', 'rules' => 'required|string'],
        ]);
        if (! $valid) {
            return $this->invalid($this->validator->getErrors());
        }

        $result = auth('session')
            ->remember(! empty($data['remember']))
            ->attempt(['email' => $email, 'password' => (string) $data['password']]);

        if (! $result->isOK()) {
            return $this->message('Email o contraseña incorrectos.', 401);
        }

        return $this->respond(['user' => $this->userData(auth('session')->user())]);
    }

    public function logout(): ResponseInterface
    {
        auth('session')->logout();

        return $this->respond(null, 204);
    }
}
