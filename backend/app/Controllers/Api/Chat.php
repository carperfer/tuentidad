<?php

namespace App\Controllers\Api;

use App\Models\FriendshipModel;
use App\Models\MessageModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Chat entre amigos por sondeo (polling): la SPA pide el estado cada cierto
 * tiempo y, con una conversación abierta, sus mensajes nuevos.
 */
class Chat extends ApiController
{
    /**
     * Segundos desde la última consulta en los que alguien cuenta como conectado.
     * La SPA consulta el estado como mucho cada 60 s, también en segundo plano.
     */
    private const ONLINE_SECONDS = 120;

    private const PAGE = 50;

    /**
     * Amigos (primero los conectados) y mensajes sin leer de cada uno.
     * Cada consulta marca al usuario como conectado.
     */
    public function status(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $db     = db_connect();
        $now    = date('Y-m-d H:i:s');
        $db->table('users')->where('id', $userId)->update(['last_active' => $now]);

        $rows = $db->table('friendships')
            ->select(self::PERSON_COLUMNS . ', users.last_active')
            ->join('users', 'users.id = friendships.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('friendships.user_id', $userId)
            // Los perfiles de bienvenida no tienen cuenta: no se puede chatear con ellos
            ->where('welcome_profiles.user_id', null)
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get()
            ->getResultArray();

        $unread = [];

        foreach ($db->table('messages')->select('sender_id, COUNT(*) AS total')->where('recipient_id', $userId)->where('read_at', null)->groupBy('sender_id')->get()->getResultArray() as $row) {
            $unread[(int) $row['sender_id']] = (int) $row['total'];
        }

        $threshold = time() - self::ONLINE_SECONDS;
        $contacts  = array_map(fn (array $row): array => [
            ...$this->personData($row, true),
            'online' => $row['last_active'] !== null && strtotime($row['last_active'] . ' UTC') >= $threshold,
            'unread' => $unread[(int) $row['id']] ?? 0,
        ], $rows);
        usort($contacts, static fn (array $a, array $b): int => $b['online'] <=> $a['online']);

        return $this->respond([
            'contacts' => $contacts,
            'online'   => count(array_filter($contacts, static fn (array $contact): bool => $contact['online'])),
            // Solo cuentan los mensajes de amigos: los de antiguos amigos no se pueden contestar
            'unread' => array_sum(array_column($contacts, 'unread')),
        ]);
    }

    /**
     * Mensajes con un amigo. Sin parámetros, los últimos 50; con ?after=<id>, los
     * posteriores a ese mensaje, y con ?before=<id>, los 50 anteriores.
     * Marca como leídos los recibidos.
     */
    public function messages(int $friendId): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (! model(FriendshipModel::class)->areFriends($userId, $friendId)) {
            return $this->message('Solo puedes chatear con tus amigos.', 403);
        }

        $after  = (int) $this->request->getGet('after');
        $before = (int) $this->request->getGet('before');
        $query  = $this->conversation($userId, $friendId);

        if ($after > 0) {
            $rows = $query->where('id >', $after)->orderBy('id')->limit(self::PAGE)->get()->getResultArray();
        } else {
            // Los últimos 50, o los 50 anteriores a ?before (historial en Mensajes)
            if ($before > 0) {
                $query->where('id <', $before);
            }
            $rows = array_reverse($query->orderBy('id', 'DESC')->limit(self::PAGE)->get()->getResultArray());
        }

        $readIds = array_column(array_filter($rows, static fn (array $row): bool => (int) $row['recipient_id'] === $userId && $row['read_at'] === null), 'id');
        if ($readIds !== []) {
            db_connect()->table('messages')->whereIn('id', $readIds)->update(['read_at' => date('Y-m-d H:i:s')]);
        }

        return $this->respond(['messages' => array_map(fn (array $row): array => $this->messageData($row, $userId), $rows)]);
    }

    /**
     * Bandeja de Mensajes: una fila por amigo con el que hay conversación,
     * con el último mensaje y los no leídos, de la más reciente a la más antigua.
     */
    public function conversations(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $db     = db_connect();
        $last   = $db->query(
            'SELECT other_id, MAX(id) AS last_id FROM ('
            . ' SELECT recipient_id AS other_id, id FROM messages WHERE sender_id = ?'
            . ' UNION ALL SELECT sender_id AS other_id, id FROM messages WHERE recipient_id = ?'
            . ') AS mine GROUP BY other_id',
            [$userId, $userId],
        )->getResultArray();

        $friends = $this->friendIds($userId);
        $lastIds = [];

        foreach ($last as $row) {
            // Solo se puede seguir la conversación con los amigos actuales
            if (in_array((int) $row['other_id'], $friends, true)) {
                $lastIds[(int) $row['other_id']] = (int) $row['last_id'];
            }
        }
        if ($lastIds === []) {
            return $this->respond(['conversations' => []]);
        }

        $messages = array_column($db->table('messages')->whereIn('id', array_values($lastIds))->get()->getResultArray(), null, 'id');
        $people   = array_column(
            $db->table('users')->select(self::PERSON_COLUMNS)->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')->whereIn('users.id', array_keys($lastIds))->get()->getResultArray(),
            null,
            'id',
        );
        $unread = [];

        foreach ($db->table('messages')->select('sender_id, COUNT(*) AS total')->where('recipient_id', $userId)->where('read_at', null)->groupBy('sender_id')->get()->getResultArray() as $row) {
            $unread[(int) $row['sender_id']] = (int) $row['total'];
        }

        arsort($lastIds);
        $conversations = [];

        foreach ($lastIds as $otherId => $messageId) {
            $conversations[] = [
                'person'       => $this->personData($people[$otherId], true),
                'last_message' => $this->messageData($messages[$messageId], $userId),
                'unread'       => $unread[$otherId] ?? 0,
            ];
        }

        return $this->respond(['conversations' => $conversations]);
    }

    public function send(int $friendId): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (! model(FriendshipModel::class)->areFriends($userId, $friendId)) {
            return $this->message('Solo puedes chatear con tus amigos.', 403);
        }
        if (($limit = $this->throttle("chat-{$userId}", 30, MINUTE)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $data  = ['body' => is_string($input['body'] ?? null) ? trim($input['body']) : ''];
        if (! $this->validateData($data, ['body' => ['label' => 'Mensaje', 'rules' => 'required|max_length[1000]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $messages = model(MessageModel::class);
        $id       = $messages->insert(['sender_id' => $userId, 'recipient_id' => $friendId, ...$data]);
        if ($id === false) {
            return $this->message('No se ha podido enviar el mensaje.', 500);
        }

        return $this->respondCreated(['message' => $this->messageData($messages->find($id), $userId)]);
    }

    private function conversation(int $userId, int $friendId): BaseBuilder
    {
        return db_connect()->table('messages')
            ->groupStart()
            ->groupStart()->where('sender_id', $userId)->where('recipient_id', $friendId)->groupEnd()
            ->orGroupStart()->where('sender_id', $friendId)->where('recipient_id', $userId)->groupEnd()
            ->groupEnd();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function messageData(array $row, int $userId): array
    {
        return [
            'id'         => (int) $row['id'],
            'sender_id'  => (int) $row['sender_id'],
            'mine'       => (int) $row['sender_id'] === $userId,
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
        ];
    }
}
