<?php

namespace App\Controllers\Api;

use App\Libraries\PhotoProcessor;
use App\Models\AlbumModel;
use App\Models\FriendshipModel;
use App\Models\PhotoModel;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Álbumes y fotos. Solo el dueño y sus amigos pueden verlos;
 * para los demás, los álbumes y fotos concretos no existen (404).
 */
class Albums extends ApiController
{
    private const MAX_PHOTO_SIZE   = 10 * 1024 * 1024;
    private const MAX_ALBUM_PHOTOS = 200;

    public function index(int $userId): ResponseInterface
    {
        if (auth()->getProvider()->findById($userId) === null) {
            return $this->message('No se ha encontrado el usuario.', 404);
        }
        if (! $this->canView($userId)) {
            return $this->message('Solo sus amigos pueden ver sus fotos.', 403);
        }

        $albums = db_connect()->table('albums')
            ->select('albums.id, albums.title, albums.description, albums.updated_at, COUNT(photos.id) AS photo_count, MIN(photos.id) AS cover_photo_id')
            ->join('photos', 'photos.album_id = albums.id', 'left')
            ->where('albums.user_id', $userId)
            ->groupBy('albums.id')
            ->orderBy('albums.created_at', 'DESC')
            ->orderBy('albums.id', 'DESC')
            ->get()
            ->getResultArray();

        $albums = array_map(static fn (array $album): array => [
            'id'             => (int) $album['id'],
            'title'          => $album['title'],
            'description'    => $album['description'],
            'updated_at'     => $album['updated_at'],
            'photo_count'    => (int) $album['photo_count'],
            'cover_photo_id' => $album['cover_photo_id'] === null ? null : (int) $album['cover_photo_id'],
        ], $albums);

        return $this->respond(['albums' => $albums]);
    }

    public function show(int $id): ResponseInterface
    {
        $album = $this->viewableAlbum($id);
        if ($album === null) {
            return $this->message('No se ha encontrado el álbum.', 404);
        }

        return $this->respond(['album' => $this->albumData($album)]);
    }

    public function create(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (($limit = $this->throttle("album-{$userId}", 30, HOUR)) !== null) {
            return $limit;
        }

        $data = $this->albumInput();
        if (! $this->validateData($data, $this->albumRules())) {
            return $this->invalid($this->validator->getErrors());
        }

        $albums = model(AlbumModel::class);
        $id     = $albums->insert(['user_id' => $userId, ...$data]);
        if ($id === false) {
            return $this->message('No se ha podido crear el álbum.', 500);
        }

        return $this->respondCreated(['album' => $this->albumData($albums->find($id))]);
    }

    public function update(int $id): ResponseInterface
    {
        $album = $this->ownAlbum($id);
        if ($album === null) {
            return $this->message('No se ha encontrado el álbum.', 404);
        }

        $data = $this->albumInput();
        if (! $this->validateData($data, $this->albumRules())) {
            return $this->invalid($this->validator->getErrors());
        }

        $albums = model(AlbumModel::class);
        if (! $albums->update($id, $data)) {
            return $this->message('No se ha podido guardar el álbum.', 500);
        }

        return $this->respond(['album' => $this->albumData($albums->find($id))]);
    }

    public function delete(int $id): ResponseInterface
    {
        $album = $this->ownAlbum($id);
        if ($album === null) {
            return $this->message('No se ha encontrado el álbum.', 404);
        }

        $filenames = array_column(model(PhotoModel::class)->select('filename')->where('album_id', $id)->findAll(), 'filename');
        model(AlbumModel::class)->delete($id);

        $processor = $this->processor();

        foreach ($filenames as $filename) {
            $processor->delete($filename);
        }

        return $this->message('Álbum borrado.');
    }

    /**
     * Sube una foto por petición: la SPA envía en serie las que se seleccionan,
     * así cada petición cabe en los límites de memoria y tamaño del hosting.
     */
    public function upload(int $id): ResponseInterface
    {
        $album = $this->ownAlbum($id);
        if ($album === null) {
            return $this->message('No se ha encontrado el álbum.', 404);
        }
        if (($limit = $this->throttle("photo-upload-{$album['user_id']}", 300, HOUR)) !== null) {
            return $limit;
        }

        $photos = model(PhotoModel::class);
        if ($photos->where('album_id', $id)->countAllResults() >= self::MAX_ALBUM_PHOTOS) {
            return $this->invalid(['photo' => 'Un álbum puede tener como máximo ' . self::MAX_ALBUM_PHOTOS . ' fotos.']);
        }

        $file = $this->request->getFile('photo');
        if ($file === null || ! $file->isValid()) {
            return $this->invalid(['photo' => 'Selecciona una imagen válida (máximo 10 MB).']);
        }
        if ($file->getSize() > self::MAX_PHOTO_SIZE) {
            return $this->invalid(['photo' => 'La foto no puede superar los 10 MB.']);
        }

        $processor = $this->processor();

        try {
            $stored = $processor->process($file->getTempName());
        } catch (InvalidArgumentException $e) {
            return $this->invalid(['photo' => $e->getMessage()]);
        }

        $photoId = $photos->insert(['album_id' => $id, ...$stored]);
        if ($photoId === false) {
            $processor->delete($stored['filename']);

            return $this->message('No se ha podido guardar la foto.', 500);
        }
        db_connect()->table('albums')->where('id', $id)->update(['updated_at' => date('Y-m-d H:i:s')]);

        return $this->respondCreated(['photo' => $this->photoData($photos->find($photoId))]);
    }

    public function deletePhoto(int $id): ResponseInterface
    {
        $photo = model(PhotoModel::class)->find($id);
        if ($photo === null || $this->ownAlbum((int) $photo['album_id']) === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        model(PhotoModel::class)->delete($id);
        $this->processor()->delete($photo['filename']);

        return $this->message('Foto borrada.');
    }

    public function image(int $id): ResponseInterface
    {
        return $this->servePhoto($id, false);
    }

    public function thumb(int $id): ResponseInterface
    {
        return $this->servePhoto($id, true);
    }

    private function servePhoto(int $id, bool $thumb): ResponseInterface
    {
        $photo = model(PhotoModel::class)->find($id);
        if ($photo === null || $this->viewableAlbum((int) $photo['album_id']) === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $processor = $this->processor();
        $path      = $thumb ? $processor->thumbPath($photo['filename']) : $processor->largePath($photo['filename']);
        if (! is_file($path)) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se ha podido leer la foto.');
        }

        // Caché corta: si se rompe la amistad, el navegador deja de mostrarla pronto
        return $this->response
            ->setContentType('image/jpeg', '')
            ->setHeader('Cache-Control', 'private, max-age=300')
            ->setBody($contents);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewableAlbum(int $id): ?array
    {
        $album = model(AlbumModel::class)->find($id);

        return $album !== null && $this->canView((int) $album['user_id']) ? $album : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownAlbum(int $id): ?array
    {
        $album = model(AlbumModel::class)->find($id);

        return $album !== null && (int) $album['user_id'] === (int) auth()->user()->id ? $album : null;
    }

    private function canView(int $ownerId): bool
    {
        $viewerId = (int) auth()->user()->id;

        return $viewerId === $ownerId || model(FriendshipModel::class)->areFriends($viewerId, $ownerId);
    }

    private function processor(): PhotoProcessor
    {
        return new PhotoProcessor(WRITEPATH . 'uploads/photos');
    }

    /**
     * @return array{title: string, description: string}
     */
    private function albumInput(): array
    {
        $input = $this->input();

        return [
            'title'       => is_string($input['title'] ?? null) ? trim($input['title']) : '',
            'description' => is_string($input['description'] ?? null) ? trim($input['description']) : '',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function albumRules(): array
    {
        return [
            'title'       => ['label' => 'Título', 'rules' => 'required|max_length[100]'],
            'description' => ['label' => 'Descripción', 'rules' => 'permit_empty|max_length[500]'],
        ];
    }

    /**
     * @param array<string, mixed> $album
     *
     * @return array<string, mixed>
     */
    private function albumData(array $album): array
    {
        $ownerId = (int) $album['user_id'];
        $owner   = auth()->getProvider()->findById($ownerId);
        $data    = [
            'id'          => (int) $album['id'],
            'title'       => $album['title'],
            'description' => $album['description'],
            'owner'       => [
                'id'         => $ownerId,
                'first_name' => $owner?->first_name,
                'last_name'  => $owner?->last_name,
            ],
            'is_owner'   => $ownerId === (int) auth()->user()->id,
            'created_at' => $album['created_at'],
            'updated_at' => $album['updated_at'],
        ];

        $photos         = model(PhotoModel::class)->where('album_id', (int) $album['id'])->orderBy('id')->findAll();
        $data['photos'] = array_map($this->photoData(...), $photos);

        return $data;
    }

    /**
     * @param array<string, mixed> $photo
     *
     * @return array<string, mixed>
     */
    private function photoData(array $photo): array
    {
        $id = (int) $photo['id'];

        return [
            'id'         => $id,
            'album_id'   => (int) $photo['album_id'],
            'width'      => (int) $photo['width'],
            'height'     => (int) $photo['height'],
            'url'        => "/api/photos/{$id}/image",
            'thumb_url'  => "/api/photos/{$id}/thumb",
            'created_at' => $photo['created_at'],
        ];
    }
}
