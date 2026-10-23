<?php

namespace App\Controllers\Api;

use App\Models\FriendshipModel;
use App\Models\UserModel;
use App\Models\WelcomeProfileModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * Consulta y edición del perfil propio y de otros usuarios.
 */
class Profiles extends ApiController
{
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024;

    public function show(int $id): ResponseInterface
    {
        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            return $this->message('No se ha encontrado el perfil.', 404);
        }

        $viewer   = auth('session')->user();
        $isOwner  = $viewer !== null && (int) $viewer->id === (int) $user->id;
        $isFriend = $viewer !== null && model(FriendshipModel::class)->areFriends((int) $viewer->id, (int) $user->id);

        return $this->respond(['profile' => $this->profileData($user, $isOwner, $isFriend)]);
    }

    public function update(): ResponseInterface
    {
        $user  = auth()->user();
        $input = $this->input();
        $data  = [
            'first_name'           => is_string($input['first_name'] ?? null) ? trim($input['first_name']) : '',
            'last_name'            => is_string($input['last_name'] ?? null) ? trim($input['last_name']) : '',
            'birthdate'            => is_string($input['birthdate'] ?? null) ? $input['birthdate'] : '',
            'bio'                  => is_string($input['bio'] ?? null) ? trim($input['bio']) : '',
            'profile_status'       => is_string($input['status'] ?? null) ? trim($input['status']) : '',
            'birthdate_visibility' => $input['birthdate_visibility'] ?? null,
            'bio_visibility'       => $input['bio_visibility'] ?? null,
            'status_visibility'    => $input['status_visibility'] ?? null,
            'avatar_visibility'    => $input['avatar_visibility'] ?? null,
        ];

        $rules = [
            'first_name'           => ['label' => 'Nombre', 'rules' => 'required|max_length[50]'],
            'last_name'            => ['label' => 'Apellidos', 'rules' => 'required|max_length[80]'],
            'birthdate'            => ['label' => 'Fecha de nacimiento', 'rules' => 'required|valid_birthdate'],
            'bio'                  => ['label' => 'Sobre mí', 'rules' => 'permit_empty|max_length[500]'],
            'profile_status'       => ['label' => 'Estado', 'rules' => 'permit_empty|max_length[160]'],
            'birthdate_visibility' => ['label' => 'Privacidad del cumpleaños', 'rules' => 'required|in_list[public,friends,private]'],
            'bio_visibility'       => ['label' => 'Privacidad de la biografía', 'rules' => 'required|in_list[public,friends,private]'],
            'status_visibility'    => ['label' => 'Privacidad del estado', 'rules' => 'required|in_list[public,friends,private]'],
            'avatar_visibility'    => ['label' => 'Privacidad de la foto', 'rules' => 'required|in_list[public,friends,private]'],
        ];

        if (! $this->validateData($data, $rules)) {
            return $this->invalid($this->validator->getErrors());
        }

        if (! model(UserModel::class)->update((int) $user->id, $data)) {
            return $this->message('No se ha podido guardar el perfil.', 500);
        }
        $updated = auth()->getProvider()->findById((int) $user->id);
        if ($updated === null) {
            throw new RuntimeException('No se ha podido volver a cargar el perfil actualizado.');
        }

        return $this->respond(['profile' => $this->profileData($updated, true, true)]);
    }

    public function uploadAvatar(): ResponseInterface
    {
        $user = auth()->user();
        if (($limit = $this->throttle("avatar-{$user->id}", 10, HOUR)) !== null) {
            return $limit;
        }

        $file = $this->request->getFile('avatar');
        if ($file === null || ! $file->isValid()) {
            return $this->invalid(['avatar' => 'Selecciona una imagen válida.']);
        }
        if ($file->getSize() > self::MAX_AVATAR_SIZE) {
            return $this->invalid(['avatar' => 'La foto no puede superar los 2 MB.']);
        }

        $mime  = $file->getMimeType();
        $image = @getimagesize($file->getTempName());
        if (! isset(self::IMAGE_TYPES[$mime]) || $image === false || $image[0] > 4000 || $image[1] > 4000) {
            return $this->invalid(['avatar' => 'Usa una imagen JPG, PNG o WebP de hasta 4000 × 4000 píxeles.']);
        }

        $directory = WRITEPATH . 'uploads/avatars';
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se ha podido crear el directorio para las fotos de perfil.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . self::IMAGE_TYPES[$mime];
        $file->move($directory, $filename);

        $previous = $user->avatar;
        if (! model(UserModel::class)->update((int) $user->id, ['avatar' => $filename])) {
            if (! unlink($directory . DIRECTORY_SEPARATOR . $filename)) {
                throw new RuntimeException('No se ha podido limpiar la foto que no llegó a guardarse.');
            }

            return $this->message('No se ha podido guardar la foto de perfil.', 500);
        }
        if (is_string($previous) && preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $previous)) {
            $oldPath = $directory . DIRECTORY_SEPARATOR . $previous;
            if (is_file($oldPath) && ! unlink($oldPath)) {
                throw new RuntimeException('No se ha podido eliminar la foto de perfil anterior.');
            }
        }

        $updated = auth()->getProvider()->findById((int) $user->id);
        if ($updated === null) {
            throw new RuntimeException('No se ha podido volver a cargar el perfil actualizado.');
        }

        return $this->respond(['profile' => $this->profileData($updated, true, true)]);
    }

    public function avatar(int $id): ResponseInterface
    {
        $user = auth()->getProvider()->findById($id);
        if ($user === null) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $welcome = model(WelcomeProfileModel::class)->where('user_id', $id)->first();
        if ($welcome !== null) {
            return redirect()->to($welcome['avatar'], 302);
        }
        if (! $this->canView($user, 'avatar')) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $filename = $user->avatar;
        if (! is_string($filename) || ! preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $filename)) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $path = WRITEPATH . 'uploads/avatars/' . $filename;
        if (! is_file($path)) {
            return $this->message('No se ha encontrado la foto.', 404);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('No se ha podido leer la foto de perfil.');
        }

        $mime = mime_content_type($path);
        if (! is_string($mime) || ! isset(self::IMAGE_TYPES[$mime])) {
            throw new RuntimeException('El tipo de foto de perfil guardado no es válido.');
        }

        return $this->response
            ->setContentType($mime)
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(User $user, bool $isOwner, bool $isFriend): array
    {
        $welcome = model(WelcomeProfileModel::class)->where('user_id', (int) $user->id)->first();
        $bio     = $welcome['bio'] ?? $this->visibleValue($user, 'bio', $isOwner, $isFriend);
        $avatar  = $welcome !== null
            ? $welcome['avatar']
            : $this->visibleValue($user, 'avatar', $isOwner, $isFriend);

        $data = [
            'id'         => (int) $user->id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'birthdate'  => $this->visibleValue($user, 'birthdate', $isOwner, $isFriend),
            'bio'        => $bio,
            'status'     => $this->visibleValue($user, 'profile_status', $isOwner, $isFriend),
            'avatar_url' => $avatar !== null && $avatar !== ''
                ? ($welcome !== null ? $welcome['avatar'] : "/api/profiles/{$user->id}/avatar")
                : null,
            'is_owner' => $isOwner,
        ];

        if ($isOwner) {
            $data['visibility'] = [
                'birthdate' => $user->birthdate_visibility ?? 'friends',
                'bio'       => $user->bio_visibility ?? 'public',
                'status'    => $user->status_visibility ?? 'public',
                'avatar'    => $user->avatar_visibility ?? 'public',
            ];
        }

        return $data;
    }

    private function visibleValue(User $user, string $field, bool $isOwner, bool $isFriend): mixed
    {
        if ($isOwner) {
            return $user->{$field};
        }

        $visibilityField = $field === 'profile_status' ? 'status' : $field;
        $visibility      = $user->{$visibilityField . '_visibility'} ?? ($field === 'birthdate' ? 'friends' : 'public');
        if ($visibility === 'private' || ($visibility === 'friends' && ! $isFriend)) {
            return null;
        }

        return $user->{$field};
    }

    private function canView(User $user, string $field): bool
    {
        $viewer   = auth('session')->user();
        $isOwner  = $viewer !== null && (int) $viewer->id === (int) $user->id;
        $isFriend = $viewer !== null && model(FriendshipModel::class)->areFriends((int) $viewer->id, (int) $user->id);

        return $isOwner || $this->visibleValue($user, $field, false, $isFriend) !== null;
    }
}
