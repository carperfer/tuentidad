<?php

namespace App\Libraries;

/**
 * Tokens de un solo uso para enlaces enviados por email.
 *
 * El token en claro solo viaja en el email; en base de datos se guarda su hash.
 */
final class Tokens
{
    /**
     * @return array{token: string, hash: string}
     */
    public static function generate(): array
    {
        $token = bin2hex(random_bytes(32));

        return ['token' => $token, 'hash' => hash('sha256', $token)];
    }

    /**
     * Formato válido de un token (evita consultas con valores arbitrarios).
     */
    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
