<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exige sesión iniciada en la API; responde 401 en JSON en lugar de redirigir.
 */
class ApiAuth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (auth('session')->loggedIn()) {
            return null;
        }

        return service('response')
            ->setStatusCode(401)
            ->setJSON(['message' => 'Tienes que iniciar sesión.']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
