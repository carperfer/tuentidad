<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Enlaces del perfil a redes sociales. Solo se admiten las conocidas, para
 * mostrar su nombre y no convertir el perfil en un escaparate de enlaces.
 */
class ProfileLinkModel extends Model
{
    public const MAX_LINKS = 8;

    /**
     * Dominio => nombre de la red.
     */
    private const NETWORKS = [
        'instagram.com' => 'Instagram',
        'x.com'         => 'X',
        'twitter.com'   => 'X',
        'tiktok.com'    => 'TikTok',
        'facebook.com'  => 'Facebook',
        'linkedin.com'  => 'LinkedIn',
        'youtube.com'   => 'YouTube',
        'twitch.tv'     => 'Twitch',
        'github.com'    => 'GitHub',
        'threads.net'   => 'Threads',
        'bsky.app'      => 'Bluesky',
    ];

    protected $table         = 'profile_links';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'url', 'position'];

    /**
     * Enlace en https a un perfil de una red conocida, o null si no lo es.
     *
     * @return array{url: string, network: string}|null
     */
    public static function normalize(string $url): ?array
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $host  = strtolower(preg_replace('/^(www\.|m\.|es\.)/', '', $parts['host'] ?? ''));
        $path  = rtrim($parts['path'] ?? '', '/');
        if (! isset(self::NETWORKS[$host]) || $path === '' || strlen($url) > 255) {
            return null;
        }

        return ['url' => "https://{$host}{$path}", 'network' => self::NETWORKS[$host]];
    }
}
