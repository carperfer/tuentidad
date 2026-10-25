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
     * Columnas que necesita personData(); la consulta debe unir
     * welcome_profiles con LEFT JOIN sobre users.id.
     */
    protected const PERSON_COLUMNS = 'users.id, users.first_name, users.last_name, users.avatar, users.avatar_visibility, welcome_profiles.avatar AS welcome_avatar';

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
            'avatar_url' => is_string($user->avatar) && $user->avatar !== '' ? "/api/profiles/{$user->id}/avatar" : null,
        ];
    }

    /**
     * Persona en un listado: nombre y foto, esta solo si su privacidad lo permite.
     *
     * @param array<string, mixed> $row Fila con PERSON_COLUMNS
     *
     * @return array{id: int, first_name: string, last_name: string, avatar_url: string|null}
     */
    protected function personData(array $row, bool $isFriend): array
    {
        $id         = (int) $row['id'];
        $visibility = $row['avatar_visibility'] ?? 'public';
        $avatarUrl  = null;

        if (is_string($row['welcome_avatar'] ?? null) && $row['welcome_avatar'] !== '') {
            $avatarUrl = $row['welcome_avatar'];
        } elseif (is_string($row['avatar'] ?? null) && $row['avatar'] !== '' && ($visibility === 'public' || ($visibility === 'friends' && $isFriend))) {
            $avatarUrl = "/api/profiles/{$id}/avatar";
        }

        return [
            'id'         => $id,
            'first_name' => (string) $row['first_name'],
            'last_name'  => (string) $row['last_name'],
            'avatar_url' => $avatarUrl,
        ];
    }

    /**
     * Ids de los amigos del usuario.
     *
     * @return list<int>
     */
    protected function friendIds(int $userId): array
    {
        $rows = db_connect()->table('friendships')->select('friend_id')->where('user_id', $userId)->get()->getResultArray();

        return array_map('intval', array_column($rows, 'friend_id'));
    }

    protected function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }
}
