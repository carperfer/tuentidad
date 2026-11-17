<?php

namespace App\Controllers\Api;

use App\Libraries\PhotoProcessor;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Derechos del RGPD sobre la cuenta propia: exportar los datos y borrarla.
 */
class Account extends ApiController
{
    /**
     * Todos los datos de la cuenta en un JSON descargable (portabilidad).
     * Las fotos se descargan desde sus URL mientras la cuenta exista.
     */
    public function export(): ResponseInterface
    {
        $user   = auth()->user();
        $userId = (int) $user->id;
        if (($limit = $this->throttle("account-export-{$userId}", 10, HOUR)) !== null) {
            return $limit;
        }

        $db   = db_connect();
        $name = static fn (string $prefix): string => "CONCAT({$prefix}.first_name, ' ', {$prefix}.last_name)";

        $albums = $this->withIntIds($db->table('albums')->select('id, title, description, created_at, updated_at')->where('user_id', $userId)->orderBy('id')->get()->getResultArray(), 'id');

        foreach ($albums as &$album) {
            $photos          = $db->table('photos')->where('album_id', $album['id'])->orderBy('id')->get()->getResultArray();
            $album['photos'] = array_map(fn (array $photo): array => [
                ...$this->photoData($photo),
                'url' => base_url("api/photos/{$photo['id']}/image"),
            ], $photos);
        }
        unset($album);

        $data = [
            'exported_at' => date('c'),
            'account'     => [
                'email'                     => $user->email,
                'first_name'                => $user->first_name,
                'last_name'                 => $user->last_name,
                'birthdate'                 => $user->birthdate,
                'bio'                       => $user->bio,
                'status'                    => $user->profile_status,
                'status_updated_at'         => $user->profile_status_updated_at,
                'has_avatar'                => is_string($user->avatar) && $user->avatar !== '',
                'privacy'                   => [
                    'birthdate' => $user->birthdate_visibility,
                    'bio'       => $user->bio_visibility,
                    'status'    => $user->status_visibility,
                    'avatar'    => $user->avatar_visibility,
                ],
                'created_at' => $user->created_at?->format('Y-m-d H:i:s'),
            ],
            'friends' => $this->withIntIds($db->table('friendships')
                ->select("users.id, {$name('users')} AS name, friendships.created_at AS since", false)
                ->join('users', 'users.id = friendships.friend_id')
                ->where('friendships.user_id', $userId)
                ->orderBy('friendships.created_at')
                ->get()->getResultArray(), 'id'),
            'friend_requests' => [
                'sent' => $this->withIntIds($db->table('friend_requests')
                    ->select("users.id, {$name('users')} AS name, friend_requests.created_at", false)
                    ->join('users', 'users.id = friend_requests.recipient_id')
                    ->where('friend_requests.sender_id', $userId)
                    ->get()->getResultArray(), 'id'),
                'received' => $this->withIntIds($db->table('friend_requests')
                    ->select("users.id, {$name('users')} AS name, friend_requests.created_at", false)
                    ->join('users', 'users.id = friend_requests.sender_id')
                    ->where('friend_requests.recipient_id', $userId)
                    ->get()->getResultArray(), 'id'),
            ],
            'invitations_sent' => $db->table('invitations')
                ->select('email, created_at, expires_at, accepted_at')
                ->where('inviter_id', $userId)
                ->orderBy('id')
                ->get()->getResultArray(),
            'albums'          => $albums,
            'photo_comments'  => $this->withIntIds($db->table('photo_comments')->select('photo_id, body, created_at')->where('user_id', $userId)->orderBy('id')->get()->getResultArray(), 'photo_id'),
            'photos_tagged_in' => $this->withIntIds($db->table('photo_tags')
                ->select("photo_tags.photo_id, {$name('taggers')} AS tagged_by, photo_tags.created_at", false)
                ->join('users AS taggers', 'taggers.id = photo_tags.tagger_id')
                ->where('photo_tags.user_id', $userId)
                ->get()->getResultArray(), 'photo_id'),
            'tags_made' => $this->withIntIds($db->table('photo_tags')
                ->select("photo_tags.photo_id, {$name('tagged')} AS person, photo_tags.created_at", false)
                ->join('users AS tagged', 'tagged.id = photo_tags.user_id')
                ->where('photo_tags.tagger_id', $userId)
                ->get()->getResultArray(), 'photo_id'),
            'messages' => $db->table('messages')
                ->select("IF(messages.sender_id = {$userId}, 'sent', 'received') AS direction, {$name('other')} AS person, messages.body, messages.created_at", false)
                ->join('users AS other', "other.id = IF(messages.sender_id = {$userId}, messages.recipient_id, messages.sender_id)", 'inner', false)
                ->groupStart()->where('messages.sender_id', $userId)->orWhere('messages.recipient_id', $userId)->groupEnd()
                ->orderBy('messages.id')
                ->get()->getResultArray(),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return $this->response
            ->setContentType('application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="tuentidad-mis-datos-' . date('Y-m-d') . '.json"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setBody($json);
    }

    /**
     * Borra la cuenta y todo lo que cuelga de ella. Pide la contraseña.
     */
    public function delete(): ResponseInterface
    {
        $user   = auth()->user();
        $userId = (int) $user->id;
        if (($limit = $this->throttle("account-delete-{$userId}", 5, 15 * MINUTE)) !== null) {
            return $limit;
        }

        $input    = $this->input();
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if ($password === '' || ! auth()->check(['email' => $user->email, 'password' => $password])->isOK()) {
            return $this->invalid(['password' => 'La contraseña no es correcta.']);
        }

        $db        = db_connect();
        $email     = (string) $user->email;
        $avatar    = $user->avatar;
        $filenames = array_column(
            $db->table('photos')->select('photos.filename')->join('albums', 'albums.id = photos.album_id')->where('albums.user_id', $userId)->get()->getResultArray(),
            'filename',
        );

        $db->transBegin();
        // Las invitaciones guardan emails y no se borran en cascada (SET NULL)
        $db->table('invitations')->groupStart()->where('inviter_id', $userId)->orWhere('user_id', $userId)->orWhere('email', $email)->groupEnd()->delete();
        $db->table('auth_logins')->groupStart()->where('user_id', $userId)->orWhere('identifier', $email)->groupEnd()->delete();
        // Borrado definitivo (Shield usa borrado lógico); el resto cae en cascada
        model(UserModel::class)->delete($userId, true);

        if (! $db->transStatus()) {
            $db->transRollback();

            return $this->message('No se ha podido borrar la cuenta.', 500);
        }
        $db->transCommit();

        auth()->logout();
        $this->deleteFiles($filenames, $avatar);

        return $this->message('Tu cuenta y todos tus datos se han borrado.');
    }

    /**
     * Convierte en enteros la columna de ids de las filas de una consulta.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withIntIds(array $rows, string $column): array
    {
        return array_map(static fn (array $row): array => [...$row, $column => (int) $row[$column]], $rows);
    }

    /**
     * @param list<string> $photoFilenames
     */
    private function deleteFiles(array $photoFilenames, mixed $avatar): void
    {
        $photos = new PhotoProcessor(WRITEPATH . 'uploads/photos');

        foreach ($photoFilenames as $filename) {
            $photos->delete($filename);
        }

        if (is_string($avatar) && preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $avatar)) {
            $path = WRITEPATH . 'uploads/avatars/' . $avatar;
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('No se ha podido borrar la foto de perfil.');
            }
        }
    }
}
