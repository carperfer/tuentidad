<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Base de los controladores de la API: entrada JSON, errores y límites de uso.
 */
abstract class ApiController extends BaseController
{
    use ResponseTrait;

    /**
     * Cuerpo de la petición (JSON o formulario).
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        try {
            $json = $this->request->getJSON(true);
        } catch (HTTPException) {
            throw new BadRequestException('El cuerpo de la petición no es un JSON válido.');
        }

        return is_array($json) ? $json : $this->request->getPost();
    }

    /**
     * 422 con los errores de validación por campo.
     *
     * @param array<string, string> $errors
     */
    protected function invalid(array $errors, string $message = 'Revisa los datos del formulario.'): ResponseInterface
    {
        return $this->respond(['message' => $message, 'errors' => $errors], 422);
    }

    protected function message(string $message, int $status = 200): ResponseInterface
    {
        return $this->respond(['message' => $message], $status);
    }

    /**
     * Devuelve una respuesta 429 si se supera el límite de peticiones para la clave.
     */
    protected function throttle(string $key, int $capacity, int $seconds): ?ResponseInterface
    {
        $throttler = service('throttler');

        if ($throttler->check(md5($key), $capacity, $seconds)) {
            return null;
        }

        return $this->message('Demasiados intentos. Espera un poco y vuelve a probar.', 429)
            ->setHeader('Retry-After', (string) max(1, $throttler->getTokenTime()));
    }

    /**
     * Datos de un usuario que se pueden enviar al cliente.
     *
     * @return array<string, mixed>
     */
    protected function userData(User $user): array
    {
        return [
            'id'         => (int) $user->id,
            'email'      => $user->email,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
        ];
    }

    protected function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }
}
