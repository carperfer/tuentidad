<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración propia de tuentidad.
 *
 * Como cualquier config de CodeIgniter, se puede sobrescribir desde .env
 * (p. ej. tuentidad.invitationQuota = 20).
 */
class Tuentidad extends BaseConfig
{
    /**
     * URL pública de la SPA para los enlaces de los emails.
     * Vacía = app.baseURL (en producción la SPA y la API comparten dominio).
     */
    public string $publicURL = '';

    /**
     * Invitaciones que puede enviar cada usuario.
     */
    public int $invitationQuota = 10;

    /**
     * Validez de una invitación, en segundos.
     */
    public int $invitationLifetime = 7 * DAY;

    /**
     * Validez de un enlace de recuperación de contraseña, en segundos.
     */
    public int $passwordResetLifetime = HOUR;

    /**
     * Edad mínima para registrarse (14 años en España, LOPDGDD art. 7).
     */
    public int $minimumAge = 14;

    /**
     * Devuelve la URL pública de la SPA para una ruta del cliente.
     */
    public function url(string $path = ''): string
    {
        $base = $this->publicURL !== '' ? $this->publicURL : config('App')->baseURL;

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
