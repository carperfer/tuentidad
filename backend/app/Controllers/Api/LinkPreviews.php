<?php

namespace App\Controllers\Api;

use App\Libraries\LinkPreviewer;
use App\Models\LinkPreviewModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Tarjetas de enlaces: la vista previa mientras se escribe en el tablón y
 * la foto de cada tarjeta (guardada aquí para no cargar nada de otras webs).
 */
class LinkPreviews extends ApiController
{
    /**
     * Tarjeta del primer enlace del texto (url), o null si no hay o no se puede leer.
     */
    public function create(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (($limit = $this->throttle("link-preview-{$userId}", 60, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $url   = is_string($input['url'] ?? null) ? LinkPreviewer::firstUrl($input['url']) : null;
        $row   = $url === null ? null : model(LinkPreviewModel::class)->forUrl($url);

        return $this->respond(['preview' => $row === null ? null : LinkPreviewModel::data($row)]);
    }

    public function image(int $id): ResponseInterface
    {
        $row = model(LinkPreviewModel::class)->find($id);
        if ($row === null || ! is_string($row['image']) || ! preg_match(LinkPreviewModel::IMAGE_PATTERN, $row['image'])) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $path = LinkPreviewModel::IMAGE_DIRECTORY . $row['image'];
        if (! is_file($path)) {
            return $this->message('No se ha encontrado la foto.', 404);
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se ha podido leer la foto del enlace.');
        }

        return $this->response
            ->setContentType('image/jpeg')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody($contents);
    }
}
