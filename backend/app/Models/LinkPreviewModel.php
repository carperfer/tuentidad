<?php

namespace App\Models;

use App\Libraries\LinkPreviewer;
use CodeIgniter\Model;
use RuntimeException;

/**
 * Tarjetas de enlaces. Cada enlace se lee una vez y la tarjeta se comparte
 * entre todos los mensajes que lo incluyan.
 */
class LinkPreviewModel extends Model
{
    public const IMAGE_DIRECTORY = WRITEPATH . 'uploads/link-previews/';
    public const IMAGE_PATTERN   = '/\A[a-f0-9]{32}\.jpg\z/';

    protected $table         = 'link_previews';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = ['url_hash', 'url', 'title', 'description', 'site_name', 'image'];
    protected $dateFormat    = 'datetime';

    /**
     * Tarjeta del enlace: la guardada o, si no hay, la que se lee de la página.
     * Null si la página no se puede leer (y no se vuelve a intentar en una hora).
     *
     * @return array<string, mixed>|null
     */
    public function forUrl(string $url): ?array
    {
        $url = LinkPreviewer::normalize($url);
        if ($url === null) {
            return null;
        }

        $hash = hash('sha256', $url);
        if (($row = $this->where('url_hash', $hash)->first()) !== null) {
            return $row;
        }
        if (cache("link-preview-miss-{$hash}") !== null) {
            return null;
        }

        $preview = service('linkPreviewer')->preview($url);
        if ($preview === null) {
            cache()->save("link-preview-miss-{$hash}", 1, HOUR);

            return null;
        }

        // Otra petición ha podido guardarla mientras se leía la página
        if (($row = $this->where('url_hash', $hash)->first()) !== null) {
            $this->deleteImage($preview['image']);

            return $row;
        }
        $id = $this->insert(['url_hash' => $hash, ...$preview]);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function data(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'url'         => $row['url'],
            'title'       => $row['title'],
            'description' => $row['description'],
            'site_name'   => $row['site_name'],
            'image_url'   => $row['image'] === null ? null : "/api/link-previews/{$row['id']}/image",
        ];
    }

    /**
     * Borra las tarjetas que ya no usa ningún mensaje (dejando una hora a
     * las que se acaban de leer mientras alguien escribe).
     */
    public function deleteUnused(): void
    {
        $rows = $this->select('link_previews.id, link_previews.image')
            ->join('wall_posts', 'wall_posts.link_preview_id = link_previews.id', 'left')
            ->where('wall_posts.id', null)
            ->where('link_previews.created_at <', date('Y-m-d H:i:s', time() - HOUR))
            ->findAll();
        if ($rows === []) {
            return;
        }

        $this->whereIn('id', array_column($rows, 'id'))->delete();

        foreach ($rows as $row) {
            $this->deleteImage($row['image']);
        }
    }

    public function deleteImage(mixed $filename): void
    {
        if (! is_string($filename) || ! preg_match(self::IMAGE_PATTERN, $filename)) {
            return;
        }
        $path = self::IMAGE_DIRECTORY . $filename;
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('No se ha podido borrar la foto de un enlace.');
        }
    }
}
