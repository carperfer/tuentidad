<?php

namespace App\Controllers\Api;

use App\Models\FriendshipModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Búsqueda, solicitudes y lista de amistades del usuario autenticado.
 */
class Friends extends ApiController
{
    public function index(): ResponseInterface
    {
        $userId  = (int) auth()->user()->id;
        $friends = db_connect()->table('friendships')
            ->select('users.id, users.first_name, users.last_name')
            ->join('users', 'users.id = friendships.friend_id')
            ->where('friendships.user_id', $userId)
            ->orderBy('users.first_name')
            ->get()
            ->getResultArray();

        return $this->respond(['friends' => $friends]);
    }

    public function activity(): ResponseInterface
    {
        $activity = db_connect()->table('friendships')
            ->select('users.id, users.first_name, users.last_name, users.profile_status AS status, users.profile_status_updated_at AS updated_at')
            ->join('users', 'users.id = friendships.friend_id')
            ->where('friendships.user_id', (int) auth()->user()->id)
            ->where('users.profile_status !=', '')
            ->whereIn('users.status_visibility', ['public', 'friends'])
            ->orderBy('users.profile_status_updated_at', 'DESC')
            ->limit(20)
            ->get()
            ->getResultArray();

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
            ->select('users.id, users.first_name, users.last_name')
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

        $friendIds   = array_map('intval', array_column($db->table('friendships')->select('friend_id')->where('user_id', $userId)->get()->getResultArray(), 'friend_id'));
        $outgoingIds = array_map('intval', array_column($db->table('friend_requests')->select('recipient_id')->where('sender_id', $userId)->get()->getResultArray(), 'recipient_id'));
        $incomingIds = array_map('intval', array_column($db->table('friend_requests')->select('sender_id')->where('recipient_id', $userId)->get()->getResultArray(), 'sender_id'));

        $users = array_map(static function (array $user) use ($friendIds, $outgoingIds, $incomingIds): array {
            $id                   = (int) $user['id'];
            $user['id']           = $id;
            $user['relationship'] = in_array($id, $friendIds, true)
              ? 'friend'
              : (in_array($id, $incomingIds, true) ? 'incoming' : (in_array($id, $outgoingIds, true) ? 'outgoing' : 'none'));

            return $user;
        }, $users);

        return $this->respond(['users' => $users]);
    }

    public function requests(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $db     = db_connect();

        $incoming = $db->table('friend_requests')
            ->select('friend_requests.id, users.id AS user_id, users.first_name, users.last_name, friend_requests.created_at')
            ->join('users', 'users.id = friend_requests.sender_id')
            ->where('friend_requests.recipient_id', $userId)
            ->orderBy('friend_requests.created_at', 'DESC')
            ->get()
            ->getResultArray();
        $outgoing = $db->table('friend_requests')
            ->select('friend_requests.id, users.id AS user_id, users.first_name, users.last_name, friend_requests.created_at')
            ->join('users', 'users.id = friend_requests.recipient_id')
            ->where('friend_requests.sender_id', $userId)
            ->orderBy('friend_requests.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->respond(['incoming' => $incoming, 'outgoing' => $outgoing]);
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

        return $this->message('Amistad eliminada.');
    }

    private function deleteRequest(int $requestId, string $ownerField): ResponseInterface
    {
        $deleted = db_connect()->table('friend_requests')->where('id', $requestId)->where($ownerField, (int) auth()->user()->id)->delete();

        return $deleted ? $this->message('Solicitud eliminada.') : $this->message('La solicitud no existe o ya fue respondida.', 404);
    }
}
