<?php

namespace App\Controllers\Api;

use App\Models\FriendshipModel;
use App\Models\NotificationModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Búsqueda, solicitudes y lista de amistades del usuario autenticado.
 */
class Friends extends ApiController
{
    public function index(): ResponseInterface
    {
        $rows = $this->friendsQuery((int) auth()->user()->id)->orderBy('users.first_name')->orderBy('users.last_name')->get()->getResultArray();

        return $this->respond(['friends' => array_map(fn (array $row): array => $this->personData($row, true), $rows)]);
    }

    /**
     * Amigos de un usuario; solo los ven él mismo y sus amigos.
     * Con ?limit=n devuelve una muestra al azar (para el perfil) y el total.
     */
    public function ofUser(int $userId): ResponseInterface
    {
        $viewerId = (int) auth()->user()->id;
        if (db_connect()->table('users')->where('id', $userId)->countAllResults() === 0) {
            return $this->message('No se ha encontrado el usuario.', 404);
        }
        if ($viewerId !== $userId && ! model(FriendshipModel::class)->areFriends($viewerId, $userId)) {
            return $this->message('Solo sus amigos pueden ver su lista de amigos.', 403);
        }

        $total = db_connect()->table('friendships')->where('user_id', $userId)->countAllResults();
        $query = $this->friendsQuery($userId);
        $this->orderOrSample($query);

        $viewerFriends = $this->friendIds($viewerId);
        $friends       = array_map(
            fn (array $row): array => $this->personData($row, $viewerId === $userId || in_array((int) $row['id'], $viewerFriends, true)),
            $query->get()->getResultArray(),
        );

        return $this->respond(['friends' => $friends, 'total' => $total]);
    }

    /**
     * Amigos que el usuario autenticado tiene en común con otro usuario.
     */
    public function mutual(int $userId): ResponseInterface
    {
        $viewerId = (int) auth()->user()->id;
        if ($viewerId === $userId) {
            return $this->respond(['friends' => [], 'total' => 0]);
        }

        $query = db_connect()->table('friendships AS mine')
            ->select(self::PERSON_COLUMNS)
            ->join('friendships AS theirs', 'theirs.friend_id = mine.friend_id')
            ->join('users', 'users.id = mine.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('mine.user_id', $viewerId)
            ->where('theirs.user_id', $userId);
        $total = (clone $query)->countAllResults();
        $this->orderOrSample($query);

        $friends = array_map(fn (array $row): array => $this->personData($row, true), $query->get()->getResultArray());

        return $this->respond(['friends' => $friends, 'total' => $total]);
    }

    public function activity(): ResponseInterface
    {
        $rows = db_connect()->table('friendships')
            ->select(self::PERSON_COLUMNS . ', users.profile_status AS status, users.profile_status_updated_at AS updated_at')
            ->join('users', 'users.id = friendships.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('friendships.user_id', (int) auth()->user()->id)
            ->where('users.profile_status !=', '')
            ->whereIn('users.status_visibility', ['public', 'friends'])
            ->orderBy('users.profile_status_updated_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $activity = array_map(fn (array $row): array => [
            ...$this->personData($row, true),
            'status'     => $row['status'],
            'updated_at' => $row['updated_at'],
        ], $rows);

        return $this->respond(['activity' => $activity]);
    }

    public function search(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $term   = trim((string) $this->request->getGet('q'));

        if (mb_strlen($term) < 2) {
            return $this->respond(['users' => []]);
        }

        $db    = db_connect();
        $users = $db->table('users')
            ->select(self::PERSON_COLUMNS)
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('users.id !=', $userId)
            ->where('welcome_profiles.user_id', null)
            ->groupStart()
            ->like('users.first_name', $term)
            ->orLike('users.last_name', $term)
            ->groupEnd()
            ->orderBy('users.first_name')
            ->limit(20)
            ->get()
            ->getResultArray();

        $friendIds   = $this->friendIds($userId);
        $outgoingIds = array_map('intval', array_column($db->table('friend_requests')->select('recipient_id')->where('sender_id', $userId)->get()->getResultArray(), 'recipient_id'));
        $incomingIds = array_map('intval', array_column($db->table('friend_requests')->select('sender_id')->where('recipient_id', $userId)->get()->getResultArray(), 'sender_id'));

        $users = array_map(function (array $row) use ($friendIds, $outgoingIds, $incomingIds): array {
            $id       = (int) $row['id'];
            $isFriend = in_array($id, $friendIds, true);

            return [
                ...$this->personData($row, $isFriend),
                'relationship' => $isFriend
                    ? 'friend'
                    : (in_array($id, $incomingIds, true) ? 'incoming' : (in_array($id, $outgoingIds, true) ? 'outgoing' : 'none')),
            ];
        }, $users);

        return $this->respond(['users' => $users]);
    }

    public function requests(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;

        return $this->respond([
            'incoming' => $this->requestList('sender_id', 'recipient_id', $userId),
            'outgoing' => $this->requestList('recipient_id', 'sender_id', $userId),
        ]);
    }

    public function request(): ResponseInterface
    {
        $input = $this->input();
        if (! $this->validateData($input, ['user_id' => ['label' => 'Usuario', 'rules' => 'required|is_natural_no_zero']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $senderId    = (int) auth()->user()->id;
        $recipientId = (int) $input['user_id'];
        $db          = db_connect();

        if ($senderId === $recipientId || $db->table('users')->where('id', $recipientId)->countAllResults() === 0) {
            return $this->message('No se puede enviar una solicitud a ese usuario.', 404);
        }
        if (model(FriendshipModel::class)->areFriends($senderId, $recipientId)) {
            return $this->message('Ya sois amigos.', 409);
        }
        if ($db->table('friend_requests')->where('sender_id', $senderId)->where('recipient_id', $recipientId)->countAllResults() > 0) {
            return $this->message('Ya has enviado una solicitud a esta persona.', 409);
        }
        if ($db->table('friend_requests')->where('sender_id', $recipientId)->where('recipient_id', $senderId)->countAllResults() > 0) {
            return $this->message('Esta persona ya te ha enviado una solicitud.', 409);
        }

        $db->table('friend_requests')->insert([
            'sender_id'    => $senderId,
            'recipient_id' => $recipientId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        // El aviso se borra en cascada cuando se acepta, rechaza o cancela la solicitud
        model(NotificationModel::class)->notify($recipientId, $senderId, 'friend_request', ['friend_request_id' => (int) $db->insertID()]);

        return $this->message('Solicitud de amistad enviada.', 201);
    }

    public function accept(int $requestId): ResponseInterface
    {
        $userId  = (int) auth()->user()->id;
        $db      = db_connect();
        $request = $db->table('friend_requests')->where('id', $requestId)->where('recipient_id', $userId)->get()->getRowArray();

        if ($request === null) {
            return $this->message('La solicitud no existe o ya fue respondida.', 404);
        }

        $db->transBegin();
        model(FriendshipModel::class)->befriend($userId, (int) $request['sender_id']);
        $db->table('friend_requests')->where('id', $requestId)->delete();

        if (! $db->transStatus()) {
            $db->transRollback();

            return $this->message('No se ha podido aceptar la solicitud.', 500);
        }

        $db->transCommit();

        return $this->message('Solicitud aceptada. Ya sois amigos.');
    }

    public function reject(int $requestId): ResponseInterface
    {
        return $this->deleteRequest($requestId, 'recipient_id');
    }

    public function cancel(int $requestId): ResponseInterface
    {
        return $this->deleteRequest($requestId, 'sender_id');
    }

    public function remove(int $friendId): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $db     = db_connect();
        $db->table('friendships')->where('user_id', $userId)->where('friend_id', $friendId)->delete();
        $db->table('friendships')->where('user_id', $friendId)->where('friend_id', $userId)->delete();

        // Ninguno de los dos puede ya ver las fotos del otro: se quitan sus etiquetas en ellas
        foreach ([[$userId, $friendId], [$friendId, $userId]] as [$tagged, $owner]) {
            $photoIds = $db->table('photos')->select('photos.id')->join('albums', 'albums.id = photos.album_id')->where('albums.user_id', $owner);
            $db->table('photo_tags')->where('user_id', $tagged)->whereIn('photo_id', $photoIds)->delete();
        }

        return $this->message('Amistad eliminada.');
    }

    private function deleteRequest(int $requestId, string $ownerField): ResponseInterface
    {
        $deleted = db_connect()->table('friend_requests')->where('id', $requestId)->where($ownerField, (int) auth()->user()->id)->delete();

        return $deleted ? $this->message('Solicitud eliminada.') : $this->message('La solicitud no existe o ya fue respondida.', 404);
    }

    private function friendsQuery(int $userId): BaseBuilder
    {
        return db_connect()->table('friendships')
            ->select(self::PERSON_COLUMNS)
            ->join('users', 'users.id = friendships.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('friendships.user_id', $userId);
    }

    /**
     * Lista completa por nombre o, con ?limit=n (1-50), una muestra al azar.
     */
    private function orderOrSample(BaseBuilder $query): void
    {
        $limit = (int) $this->request->getGet('limit');
        if ($limit >= 1) {
            $query->orderBy('', 'RANDOM', false)->limit(min($limit, 50));
        } else {
            $query->orderBy('users.first_name')->orderBy('users.last_name');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requestList(string $personField, string $ownerField, int $userId): array
    {
        $rows = db_connect()->table('friend_requests')
            ->select('friend_requests.id AS request_id, friend_requests.created_at, ' . self::PERSON_COLUMNS)
            ->join('users', "users.id = friend_requests.{$personField}")
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where("friend_requests.{$ownerField}", $userId)
            ->orderBy('friend_requests.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(function (array $row): array {
            $person = $this->personData($row, false);

            return [
                'id'         => (int) $row['request_id'],
                'user_id'    => $person['id'],
                'first_name' => $person['first_name'],
                'last_name'  => $person['last_name'],
                'avatar_url' => $person['avatar_url'],
                'created_at' => $row['created_at'],
            ];
        }, $rows);
    }
}
