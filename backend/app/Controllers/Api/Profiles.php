<?php

namespace App\Controllers\Api;

use App\Libraries\PhotoProcessor;
use App\Models\FriendshipModel;
use App\Models\ProfileLinkModel;
use App\Models\ProfileNetworkModel;
use App\Models\UserModel;
use App\Models\WelcomeProfileModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
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
    private const AVATAR_SIZE     = 600;

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

        if (($user->profile_status ?? '') !== $data['profile_status']) {
            $data['profile_status_updated_at'] = date('Y-m-d H:i:s');
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

    /**
     * Cambia solo el estado (barra «¿Cómo va todo?» de la página de inicio).
     */
    /**
     * «Redes»: estudios, trayectoria profesional y colegios con sus años.
     * Sustituye la lista entera; visibility es la privacidad del apartado.
     */
    public function updateNetworks(): ResponseInterface
    {
        $input = $this->input();
        $items = is_array($input['items'] ?? null) ? array_values($input['items']) : [];
        if (($error = $this->visibilityError($input['visibility'] ?? null)) !== null) {
            return $this->invalid(['visibility' => $error]);
        }
        if (count($items) > ProfileNetworkModel::MAX_ITEMS) {
            return $this->invalid(['items' => 'Puedes añadir como máximo ' . ProfileNetworkModel::MAX_ITEMS . ' estudios o trabajos.']);
        }

        $maxYear = (int) date('Y') + 1;
        $rows    = [];
        $errors  = [];

        foreach ($items as $index => $item) {
            $item  = is_array($item) ? $item : [];
            $row   = [
                'kind'       => $item['kind'] ?? null,
                'name'       => is_string($item['name'] ?? null) ? trim($item['name']) : '',
                'detail'     => is_string($item['detail'] ?? null) ? trim($item['detail']) : '',
                'start_year' => is_numeric($item['start_year'] ?? null) ? (int) $item['start_year'] : null,
                'end_year'   => is_numeric($item['end_year'] ?? null) ? (int) $item['end_year'] : null,
            ];
            $error = match (true) {
                ! in_array($row['kind'], ProfileNetworkModel::KINDS, true) => 'Elige si son estudios, un trabajo o un colegio.',
                $row['name'] === '' || mb_strlen($row['name']) > 120      => 'Escribe el nombre (hasta 120 caracteres).',
                mb_strlen($row['detail']) > 150                           => 'El detalle no puede pasar de 150 caracteres.',
                $row['start_year'] !== null && ($row['start_year'] < 1940 || $row['start_year'] > $maxYear) => 'Revisa el año de inicio.',
                $row['end_year'] !== null && ($row['end_year'] < 1940 || $row['end_year'] > $maxYear)       => 'Revisa el año de fin.',
                $row['start_year'] !== null && $row['end_year'] !== null && $row['end_year'] < $row['start_year'] => 'El año de fin no puede ser anterior al de inicio.',
                default => null,
            };
            if ($error !== null) {
                $errors["items.{$index}"] = $error;
            }
            $rows[] = $row;
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $userId = (int) auth()->user()->id;
        $db     = db_connect();
        $db->transStart();
        $db->table('profile_networks')->where('user_id', $userId)->delete();

        foreach ($rows as $position => $row) {
            $db->table('profile_networks')->insert(['user_id' => $userId, 'position' => $position, ...$row]);
        }
        model(UserModel::class)->update($userId, ['networks_visibility' => $input['visibility']]);
        $db->transComplete();

        return $this->respondWithOwnProfile();
    }

    /**
     * Enlaces a redes sociales. Sustituye la lista entera.
     */
    public function updateLinks(): ResponseInterface
    {
        $input = $this->input();
        $urls  = is_array($input['links'] ?? null) ? array_values(array_filter($input['links'], static fn ($url): bool => is_string($url) && trim($url) !== '')) : [];
        if (($error = $this->visibilityError($input['visibility'] ?? null)) !== null) {
            return $this->invalid(['visibility' => $error]);
        }
        if (count($urls) > ProfileLinkModel::MAX_LINKS) {
            return $this->invalid(['links' => 'Puedes añadir como máximo ' . ProfileLinkModel::MAX_LINKS . ' enlaces.']);
        }

        $links  = [];
        $errors = [];

        foreach ($urls as $index => $url) {
            $link = ProfileLinkModel::normalize($url);
            if ($link === null) {
                $errors["links.{$index}"] = 'Pega el enlace a tu perfil de Instagram, X, TikTok, Facebook, LinkedIn, YouTube, Twitch, GitHub, Threads o Bluesky.';
            } else {
                $links[$link['url']] = $link['url'];
            }
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $userId = (int) auth()->user()->id;
        $db     = db_connect();
        $db->transStart();
        $db->table('profile_links')->where('user_id', $userId)->delete();

        foreach (array_values($links) as $position => $url) {
            $db->table('profile_links')->insert(['user_id' => $userId, 'url' => $url, 'position' => $position]);
        }
        model(UserModel::class)->update($userId, ['links_visibility' => $input['visibility']]);
        $db->transComplete();

        return $this->respondWithOwnProfile();
    }

    /**
     * Ajustes de la cuenta: por ahora, la frecuencia de los emails de resumen.
     */
    public function updateSettings(): ResponseInterface
    {
        $user   = auth()->user();
        $digest = $this->input()['digest'] ?? null;
        if (! in_array($digest, ['none', 'daily', 'weekly'], true)) {
            return $this->invalid(['digest' => 'Elige nunca, cada día o cada semana.']);
        }

        // Al activarlo, el primer resumen solo incluye lo que llegue a partir de ahora
        $data = ['digest' => $digest];
        if (($user->digest ?? 'none') === 'none' && $digest !== 'none') {
            $data['digest_sent_at'] = date('Y-m-d H:i:s');
        }
        if (! model(UserModel::class)->update((int) $user->id, $data)) {
            return $this->message('No se han podido guardar los ajustes.', 500);
        }

        $updated = auth()->getProvider()->findById((int) $user->id);
        if ($updated === null) {
            throw new RuntimeException('No se ha podido volver a cargar el perfil actualizado.');
        }

        return $this->respond(['profile' => $this->profileData($updated, true, true)]);
    }

    public function updateStatus(): ResponseInterface
    {
        $user   = auth()->user();
        $input  = $this->input();
        $status = is_string($input['status'] ?? null) ? trim($input['status']) : '';

        if (! $this->validateData(['status' => $status], ['status' => ['label' => 'Estado', 'rules' => 'permit_empty|max_length[160]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        if (($user->profile_status ?? '') !== $status) {
            $data = ['profile_status' => $status, 'profile_status_updated_at' => date('Y-m-d H:i:s')];
            if (! model(UserModel::class)->update((int) $user->id, $data)) {
                return $this->message('No se ha podido guardar el estado.', 500);
            }
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

        // Se vuelve a generar en JPEG: así se descartan los metadatos EXIF (ubicación incluida)
        $directory = WRITEPATH . 'uploads/avatars';

        try {
            $filename = (new PhotoProcessor($directory))->processSingle($file->getTempName(), self::AVATAR_SIZE);
        } catch (InvalidArgumentException $e) {
            return $this->invalid(['avatar' => $e->getMessage()]);
        }

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

        $status = $this->visibleValue($user, 'profile_status', $isOwner, $isFriend);
        $data   = [
            'id'                => (int) $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'birthdate'         => $this->visibleValue($user, 'birthdate', $isOwner, $isFriend),
            'bio'               => $bio,
            'status'            => $status,
            'status_updated_at' => $status !== null && $status !== '' ? $user->profile_status_updated_at : null,
            'avatar_url'        => $avatar !== null && $avatar !== ''
                ? ($welcome !== null ? $welcome['avatar'] : "/api/profiles/{$user->id}/avatar")
                : null,
            'is_owner' => $isOwner,
            ...$this->relationship((int) $user->id, $isOwner, $isFriend),
            'networks' => $welcome === null && $this->sectionVisible($user, 'networks', $isOwner, $isFriend) ? $this->networks((int) $user->id) : null,
            'links'    => $welcome === null && $this->sectionVisible($user, 'links', $isOwner, $isFriend) ? $this->links((int) $user->id) : null,
        ];

        if ($isOwner) {
            $data['visibility'] = [
                'birthdate' => $user->birthdate_visibility ?? 'friends',
                'bio'       => $user->bio_visibility ?? 'public',
                'status'    => $user->status_visibility ?? 'public',
                'avatar'    => $user->avatar_visibility ?? 'public',
                'networks'  => $user->networks_visibility ?? 'friends',
                'links'     => $user->links_visibility ?? 'friends',
            ];
            $data['digest'] = $user->digest ?? 'none';
        }

        return $data;
    }

    /**
     * Relación del usuario autenticado con el perfil y, si hay una solicitud
     * pendiente entre ambos, su id para aceptarla o cancelarla.
     *
     * @return array{relationship: string, request_id: int|null}
     */
    private function relationship(int $profileId, bool $isOwner, bool $isFriend): array
    {
        $viewer = auth('session')->user();
        if ($isOwner || $viewer === null) {
            return ['relationship' => 'self', 'request_id' => null];
        }
        if ($isFriend) {
            return ['relationship' => 'friend', 'request_id' => null];
        }

        $viewerId = (int) $viewer->id;
        $request  = db_connect()->table('friend_requests')
            ->groupStart()->where('sender_id', $viewerId)->where('recipient_id', $profileId)->groupEnd()
            ->orGroupStart()->where('sender_id', $profileId)->where('recipient_id', $viewerId)->groupEnd()
            ->get()
            ->getRowArray();

        if ($request === null) {
            return ['relationship' => 'none', 'request_id' => null];
        }

        return [
            'relationship' => (int) $request['sender_id'] === $viewerId ? 'outgoing' : 'incoming',
            'request_id'   => (int) $request['id'],
        ];
    }

    /**
     * Apartados enteros del perfil (Redes, enlaces) con su propia privacidad;
     * por defecto, solo para amigos.
     */
    private function sectionVisible(User $user, string $section, bool $isOwner, bool $isFriend): bool
    {
        $visibility = $user->{$section . '_visibility'} ?? 'friends';

        return $isOwner || $visibility === 'public' || ($visibility === 'friends' && $isFriend);
    }

    /**
     * @return array<string, list<array<string, mixed>>> university, work y school
     */
    private function networks(int $userId): array
    {
        $groups = array_fill_keys(ProfileNetworkModel::KINDS, []);
        $rows   = model(ProfileNetworkModel::class)->where('user_id', $userId)->orderBy('position')->findAll();

        foreach ($rows as $row) {
            $groups[$row['kind']][] = [
                'name'       => $row['name'],
                'detail'     => $row['detail'],
                'start_year' => $row['start_year'] === null ? null : (int) $row['start_year'],
                'end_year'   => $row['end_year'] === null ? null : (int) $row['end_year'],
            ];
        }

        return $groups;
    }

    /**
     * @return list<array{url: string, network: string}>
     */
    private function links(int $userId): array
    {
        $rows = model(ProfileLinkModel::class)->where('user_id', $userId)->orderBy('position')->findAll();

        return array_values(array_filter(array_map(static fn (array $row): ?array => ProfileLinkModel::normalize($row['url']), $rows)));
    }

    private function visibilityError(mixed $visibility): ?string
    {
        return in_array($visibility, ['public', 'friends', 'private'], true) ? null : 'Elige quién puede verlo.';
    }

    private function respondWithOwnProfile(): ResponseInterface
    {
        $updated = auth()->getProvider()->findById((int) auth()->user()->id);
        if ($updated === null) {
            throw new RuntimeException('No se ha podido volver a cargar el perfil actualizado.');
        }

        return $this->respond(['profile' => $this->profileData($updated, true, true)]);
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
