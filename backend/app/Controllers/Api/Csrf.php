<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Entrega a la SPA el token CSRF vigente.
 *
 * La cookie CSRF es HttpOnly, así que el cliente obtiene aquí el token
 * y lo reenvía en la cabecera configurada en las peticiones que modifican datos.
 */
class Csrf extends BaseController
{
    use ResponseTrait;

    public function index(): ResponseInterface
    {
        return $this->respond([
            'header' => csrf_header(),
            'token'  => csrf_hash(),
        ]);
    }
}
