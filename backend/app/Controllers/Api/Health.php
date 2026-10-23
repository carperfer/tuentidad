<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Comprobación de estado de la API y de la conexión a base de datos.
 */
class Health extends BaseController
{
    use ResponseTrait;

    public function index(): ResponseInterface
    {
        $database = 'ok';

        try {
            db_connect()->query('SELECT 1');
        } catch (Throwable) {
            $database = 'error';
        }

        $status = $database === 'ok' ? 'ok' : 'degraded';

        return $this->respond([
            'status'   => $status,
            'database' => $database,
            'time'     => date(DATE_ATOM),
        ], $status === 'ok' ? 200 : 503);
    }
}
