<?php

namespace App\Controllers\Api;

use App\Libraries\UserCreator;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Tareas de despliegue que en un servidor con SSH se harían con spark.
 *
 * El alojamiento no tiene consola, así que el workflow de despliegue (o una
 * persona con el token) las lanza por HTTP con "Authorization: Bearer <token>".
 * Sin tuentidad.deployToken configurado, los endpoints no existen (404).
 */
class Deploy extends ApiController
{
    /**
     * Aplica las migraciones pendientes de todos los módulos.
     */
    public function migrate(): ResponseInterface
    {
        if (($denied = $this->authorize()) !== null) {
            return $denied;
        }

        $runner = service('migrations');
        $runner->setNamespace(null);

        if (! $runner->latest()) {
            return $this->message('No se han podido aplicar las migraciones.', 500);
        }

        return $this->respond([
            'message'    => 'Migraciones aplicadas.',
            'migrations' => count($runner->getHistory('')),
        ]);
    }

    /**
     * Crea el primer usuario. Solo funciona mientras no exista ninguno.
     */
    public function firstUser(): ResponseInterface
    {
        if (($denied = $this->authorize()) !== null) {
            return $denied;
        }

        if (db_connect()->table('users')->countAllResults() > 0) {
            return $this->message('Ya existen usuarios: este endpoint solo sirve para crear el primero.', 409);
        }

        $creator = new UserCreator();
        $user    = $creator->create($this->input());

        if ($user === null) {
            return $this->invalid($creator->errors());
        }

        return $this->respond(['user' => $this->userData($user)], 201);
    }

    private function authorize(): ?ResponseInterface
    {
        $expected = config('Tuentidad')->deployToken;

        if (! preg_match('/^[a-f0-9]{64}$/', $expected)) {
            return $this->message('No encontrado.', 404);
        }

        if (($limit = $this->throttle('deploy-' . $this->request->getIPAddress(), 10, MINUTE)) !== null) {
            return $limit;
        }

        $header = $this->request->getHeaderLine('Authorization');
        $token  = str_starts_with($header, 'Bearer ') ? substr($header, 7) : '';

        if (! hash_equals($expected, $token)) {
            return $this->message('Token de despliegue no válido.', 401);
        }

        return null;
    }
}
