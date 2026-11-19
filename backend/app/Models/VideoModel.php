<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Vídeos de YouTube que comparte cada usuario.
 */
class VideoModel extends Model
{
    protected $table         = 'videos';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['user_id', 'youtube_id', 'title'];
    protected $dateFormat    = 'datetime';

    /**
     * Id de un vídeo a partir de un enlace de YouTube (youtube.com/watch?v=,
     * youtu.be/, /shorts/, /embed/) o null si no lo es.
     */
    public static function youtubeId(string $url): ?string
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $host  = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host'] ?? ''));
        $path  = $parts['path'] ?? '';
        $id    = null;

        if ($host === 'youtu.be') {
            $id = explode('/', trim($path, '/'))[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com', 'music.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $path === '/watch' ? ($query['v'] ?? null) : null;
            if ($id === null && preg_match('#^/(shorts|embed|live)/([^/?]+)#', $path, $matches)) {
                $id = $matches[2];
            }
        }

        return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $id) ? $id : null;
    }
}
