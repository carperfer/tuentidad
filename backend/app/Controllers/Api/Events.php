<?php

namespace App\Controllers\Api;

use App\Models\EventCommentModel;
use App\Models\EventInvitationModel;
use App\Models\EventModel;
use App\Models\NotificationModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Eventos entre amigos. Solo los ven quien organiza y sus invitados (para
 * los demás no existen: 404). Las fechas viajan en UTC.
 */
class Events extends ApiController
{
    /**
     * Horas tras el inicio en que un evento sigue contando como próximo.
     */
    private const ONGOING_HOURS = 6;

    private const MAX_INVITEES = 200;

    public function index(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $limit  = $this->ongoingLimit();

        $upcoming = $this->mine($userId)->where('events.starts_at >=', $limit)->orderBy('events.starts_at')->get()->getResultArray();
        $past     = $this->mine($userId)->where('events.starts_at <', $limit)->orderBy('events.starts_at', 'DESC')->limit(20)->get()->getResultArray();

        return $this->respond([
            'upcoming' => array_map(fn (array $event): array => $this->eventData($event, $userId), $upcoming),
            'past'     => array_map(fn (array $event): array => $this->eventData($event, $userId), $past),
        ]);
    }

    /**
     * Módulo «Calendario» de Inicio: eventos de los próximos 8 días a los que
     * no se ha dicho que no y cumpleaños de amigos que lo dejan ver.
     */
    public function calendar(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        $events = $this->mine($userId)
            ->where('events.starts_at >=', $this->ongoingLimit())
            ->where('events.starts_at <', date('Y-m-d H:i:s', strtotime('+8 days')))
            ->groupStart()->where('my_invitation.status', null)->orWhere('my_invitation.status !=', 'no')->groupEnd()
            ->orderBy('events.starts_at')
            ->get()
            ->getResultArray();

        $friends = db_connect()->table('friendships')
            ->select(self::PERSON_COLUMNS . ', users.birthdate')
            ->join('users', 'users.id = friendships.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('friendships.user_id', $userId)
            ->where('welcome_profiles.user_id', null)
            ->where('users.birthdate IS NOT NULL', null, false)
            ->whereIn('users.birthdate_visibility', ['public', 'friends'])
            ->get()
            ->getResultArray();

        $today     = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $birthdays = [];

        foreach ($friends as $friend) {
            [, $month, $day] = array_map('intval', explode('-', (string) $friend['birthdate']));
            $next            = $today->setDate((int) $today->format('Y'), $month, $day);
            if ($next < $today) {
                $next = $next->modify('+1 year');
            }
            if ($next->diff($today)->days < 8) {
                $birthdays[] = ['person' => $this->personData($friend, true), 'date' => $next->format('Y-m-d')];
            }
        }
        usort($birthdays, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $this->respond([
            'events'    => array_map(fn (array $event): array => $this->eventData($event, $userId), $events),
            'birthdays' => $birthdays,
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $event = $this->viewableEvent($id);
        if ($event === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        // Al verlo se marcan como leídos sus avisos
        model(NotificationModel::class)
            ->where('user_id', (int) auth()->user()->id)
            ->where('event_id', $id)
            ->where('read_at', null)
            ->set('read_at', date('Y-m-d H:i:s'))
            ->update();

        return $this->respond(['event' => $this->fullData($event)]);
    }

    public function create(): ResponseInterface
    {
        $userId = (int) auth()->user()->id;
        if (($limit = $this->throttle("event-{$userId}", 20, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $data  = $this->eventInput($input);
        if (($errors = $this->eventErrors($data, true)) !== []) {
            return $this->invalid($errors);
        }

        $inviteIds = $this->idList($input['invite'] ?? []);
        if (($errors = $this->inviteErrors($userId, $inviteIds)) !== []) {
            return $this->invalid($errors);
        }

        $db = db_connect();
        $db->transBegin();
        $eventId = (int) model(EventModel::class)->insert(['user_id' => $userId, ...$data]);
        $this->invite($eventId, $userId, $inviteIds);
        if (! $db->transStatus()) {
            $db->transRollback();

            return $this->message('No se ha podido crear el evento.', 500);
        }
        $db->transCommit();

        return $this->respondCreated(['event' => $this->fullData($this->viewableEvent($eventId))]);
    }

    public function update(int $id): ResponseInterface
    {
        $event = $this->ownEvent($id);
        if ($event === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        $data = $this->eventInput($this->input());
        // Un evento ya pasado se puede corregir sin moverlo al futuro
        $moved = $data['starts_at'] !== $event['starts_at'];
        if (($errors = $this->eventErrors($data, $moved)) !== []) {
            return $this->invalid($errors);
        }
        if ($moved) {
            // Con la nueva fecha, vuelve a tocar recordatorio
            $data['reminded_at'] = null;
        }

        model(EventModel::class)->update($id, $data);

        return $this->respond(['event' => $this->fullData($this->viewableEvent($id))]);
    }

    public function delete(int $id): ResponseInterface
    {
        if ($this->ownEvent($id) === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        model(EventModel::class)->delete($id);

        return $this->message('Evento borrado.');
    }

    /**
     * Amigos de quien organiza que aún se pueden invitar (sin evento, todos).
     */
    public function invitable(): ResponseInterface
    {
        $userId  = (int) auth()->user()->id;
        $eventId = (int) $this->request->getGet('event');
        if ($eventId > 0 && $this->ownEvent($eventId) === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        $query = $this->invitableQuery($userId)->orderBy('users.first_name')->orderBy('users.last_name');
        if ($eventId > 0) {
            $invited = db_connect()->table('event_invitations')->select('user_id')->where('event_id', $eventId);
            $query->whereNotIn('users.id', $invited);
        }

        return $this->respond(['people' => array_map(fn (array $row): array => $this->personData($row, true), $query->get()->getResultArray())]);
    }

    public function inviteMore(int $id): ResponseInterface
    {
        $event = $this->ownEvent($id);
        if ($event === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        $userId = (int) auth()->user()->id;
        $ids    = $this->idList($this->input()['user_ids'] ?? []);
        if ($ids === []) {
            return $this->invalid(['user_ids' => 'Elige al menos a un amigo.']);
        }
        if (($errors = $this->inviteErrors($userId, $ids, $id)) !== []) {
            return $this->invalid($errors);
        }

        $this->invite($id, $userId, $ids);

        return $this->respond(['event' => $this->fullData($this->viewableEvent($id))]);
    }

    public function rsvp(int $id): ResponseInterface
    {
        $event  = $this->viewableEvent($id);
        $userId = (int) auth()->user()->id;
        if ($event === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }
        if ((int) $event['user_id'] === $userId) {
            return $this->message('Organizas este evento: ya cuentas como asistente.', 409);
        }

        $status = $this->input()['status'] ?? null;
        if (! in_array($status, EventInvitationModel::STATUSES, true)) {
            return $this->invalid(['status' => 'Responde sí, no o quizás.']);
        }

        db_connect()->table('event_invitations')
            ->where('event_id', $id)
            ->where('user_id', $userId)
            ->update(['status' => $status, 'responded_at' => date('Y-m-d H:i:s')]);

        return $this->respond(['event' => $this->fullData($this->viewableEvent($id))]);
    }

    public function comment(int $id): ResponseInterface
    {
        $event = $this->viewableEvent($id);
        if ($event === null) {
            return $this->message('No se ha encontrado el evento.', 404);
        }

        $authorId = (int) auth()->user()->id;
        if (($limit = $this->throttle("event-comment-{$authorId}", 60, HOUR)) !== null) {
            return $limit;
        }

        $input = $this->input();
        $data  = ['body' => is_string($input['body'] ?? null) ? trim($input['body']) : ''];
        if (! $this->validateData($data, ['body' => ['label' => 'Comentario', 'rules' => 'required|max_length[500]']])) {
            return $this->invalid($this->validator->getErrors());
        }

        $commentId = (int) model(EventCommentModel::class)->insert(['event_id' => $id, 'user_id' => $authorId, ...$data]);

        // Avisa a quien organiza y a quienes van o quizás van
        $attending = array_map('intval', array_column(
            db_connect()->table('event_invitations')->select('user_id')->where('event_id', $id)->whereIn('status', ['yes', 'maybe'])->get()->getResultArray(),
            'user_id',
        ));

        foreach (array_unique([(int) $event['user_id'], ...$attending]) as $recipientId) {
            model(NotificationModel::class)->notify($recipientId, $authorId, 'event_comment', ['event_id' => $id, 'event_comment_id' => $commentId]);
        }

        return $this->respondCreated(['comments' => $this->commentList($event)]);
    }

    /**
     * Borran un comentario su autor y quien organiza el evento.
     */
    public function deleteComment(int $commentId): ResponseInterface
    {
        $comment = model(EventCommentModel::class)->find($commentId);
        $event   = $comment === null ? null : $this->viewableEvent((int) $comment['event_id']);
        $userId  = (int) auth()->user()->id;
        if ($comment === null || $event === null || ($userId !== (int) $comment['user_id'] && $userId !== (int) $event['user_id'])) {
            return $this->message('No se ha encontrado el comentario.', 404);
        }

        model(EventCommentModel::class)->delete($commentId);

        return $this->respond(['message' => 'Comentario borrado.', 'comments' => $this->commentList($event)]);
    }

    /**
     * Eventos que organiza el usuario o a los que está invitado, con su respuesta
     * y los datos de quien organiza.
     */
    private function mine(int $userId): BaseBuilder
    {
        return db_connect()->table('events')
            // users.id (de PERSON_COLUMNS) pisa events.id: el del evento va en event_id
            ->select('events.*, events.id AS event_id, my_invitation.status AS my_status, ' . self::PERSON_COLUMNS)
            ->join('event_invitations AS my_invitation', "my_invitation.event_id = events.id AND my_invitation.user_id = {$userId}", 'left')
            ->join('users', 'users.id = events.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->groupStart()->where('events.user_id', $userId)->orWhere('my_invitation.id IS NOT NULL', null, false)->groupEnd();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewableEvent(int $id): ?array
    {
        return $this->mine((int) auth()->user()->id)->where('events.id', $id)->get()->getRowArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownEvent(int $id): ?array
    {
        $event = model(EventModel::class)->find($id);

        return $event !== null && (int) $event['user_id'] === (int) auth()->user()->id ? $event : null;
    }

    private function ongoingLimit(): string
    {
        return date('Y-m-d H:i:s', time() - self::ONGOING_HOURS * HOUR);
    }

    /**
     * Amigos de alguien que se pueden invitar: no los perfiles de bienvenida.
     */
    private function invitableQuery(int $userId): BaseBuilder
    {
        return db_connect()->table('friendships')
            ->select(self::PERSON_COLUMNS)
            ->join('users', 'users.id = friendships.friend_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('friendships.user_id', $userId)
            ->where('welcome_profiles.user_id', null);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<string, string>
     */
    private function inviteErrors(int $organizerId, array $ids, ?int $eventId = null): array
    {
        if ($ids === []) {
            return [];
        }

        $invited = $eventId === null ? 0 : db_connect()->table('event_invitations')->where('event_id', $eventId)->countAllResults();
        if ($invited + count($ids) > self::MAX_INVITEES) {
            return ['invite' => 'Un evento puede tener como máximo ' . self::MAX_INVITEES . ' invitados.'];
        }

        $valid = array_map('intval', array_column($this->invitableQuery($organizerId)->whereIn('users.id', $ids)->get()->getResultArray(), 'id'));

        return array_diff($ids, $valid) === [] ? [] : ['invite' => 'Solo puedes invitar a tus amigos.'];
    }

    /**
     * Invita (sin repetir) y avisa a cada invitado.
     *
     * @param list<int> $ids
     */
    private function invite(int $eventId, int $organizerId, array $ids): void
    {
        $db  = db_connect();
        $now = date('Y-m-d H:i:s');

        foreach ($ids as $userId) {
            $db->table('event_invitations')->ignore(true)->insert(['event_id' => $eventId, 'user_id' => $userId, 'status' => 'pending', 'created_at' => $now]);
            if ($db->affectedRows() === 1) {
                model(NotificationModel::class)->notify($userId, $organizerId, 'event_invite', ['event_id' => $eventId]);
            }
        }
    }

    /**
     * @return list<int>
     */
    private function idList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{title: string, place: string, description: string, starts_at: string}
     */
    private function eventInput(array $input): array
    {
        $startsAt = is_string($input['starts_at'] ?? null) ? strtotime($input['starts_at']) : false;

        return [
            'title'       => is_string($input['title'] ?? null) ? trim($input['title']) : '',
            'place'       => is_string($input['place'] ?? null) ? trim($input['place']) : '',
            'description' => is_string($input['description'] ?? null) ? trim($input['description']) : '',
            'starts_at'   => $startsAt === false ? '' : date('Y-m-d H:i:s', $startsAt),
        ];
    }

    /**
     * @param array<string, string> $data
     *
     * @return array<string, string>
     */
    private function eventErrors(array $data, bool $mustBeFuture): array
    {
        $rules = [
            'title'       => ['label' => 'Título', 'rules' => 'required|max_length[100]'],
            'place'       => ['label' => 'Lugar', 'rules' => 'permit_empty|max_length[150]'],
            'description' => ['label' => 'Descripción', 'rules' => 'permit_empty|max_length[2000]'],
            'starts_at'   => ['label' => 'Fecha', 'rules' => 'required', 'errors' => ['required' => 'Indica el día y la hora.']],
        ];
        if (! $this->validateData($data, $rules)) {
            return $this->validator->getErrors();
        }

        $startsAt = strtotime($data['starts_at'] . ' UTC');
        if ($mustBeFuture && $startsAt < time() - HOUR) {
            return ['starts_at' => 'El evento tiene que ser en el futuro.'];
        }
        if ($startsAt > strtotime('+2 years')) {
            return ['starts_at' => 'El evento no puede ser dentro de más de dos años.'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $event Fila de mine()
     *
     * @return array<string, mixed>
     */
    private function eventData(array $event, int $userId): array
    {
        $isOrganizer = (int) $event['user_id'] === $userId;
        $counts      = array_fill_keys(['yes', 'maybe', 'no', 'pending'], 0);

        foreach (db_connect()->table('event_invitations')->select('status, COUNT(*) AS total')->where('event_id', $event['event_id'])->groupBy('status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return [
            'id'           => (int) $event['event_id'],
            'title'        => $event['title'],
            'place'        => $event['place'],
            'description'  => $event['description'],
            'starts_at'    => $event['starts_at'],
            'organizer'    => $this->personData($event, $isOrganizer || in_array((int) $event['user_id'], $this->friendIds($userId), true)),
            'is_organizer' => $isOrganizer,
            'my_status'    => $isOrganizer ? 'organizer' : $event['my_status'],
            // Quien organiza cuenta entre los que van
            'counts' => [...$counts, 'yes' => $counts['yes'] + 1],
        ];
    }

    /**
     * Evento con sus invitados y comentarios (para su página).
     *
     * @param array<string, mixed> $event Fila de mine()
     *
     * @return array<string, mixed>
     */
    private function fullData(array $event): array
    {
        $userId  = (int) auth()->user()->id;
        $friends = $this->photoOwnersVisibleTo($userId);
        $rows    = db_connect()->table('event_invitations')
            ->select('event_invitations.status, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = event_invitations.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('event_invitations.event_id', $event['event_id'])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get()
            ->getResultArray();

        return [
            ...$this->eventData($event, $userId),
            'invitees' => array_map(fn (array $row): array => [
                'person' => $this->personData($row, in_array((int) $row['id'], $friends, true)),
                'status' => $row['status'],
            ], $rows),
            'comments' => $this->commentList($event),
        ];
    }

    /**
     * @param array<string, mixed> $event
     *
     * @return list<array<string, mixed>>
     */
    private function commentList(array $event): array
    {
        $rows = db_connect()->table('event_comments')
            ->select('event_comments.id AS comment_id, event_comments.body, event_comments.created_at, ' . self::PERSON_COLUMNS)
            ->join('users', 'users.id = event_comments.user_id')
            ->join('welcome_profiles', 'welcome_profiles.user_id = users.id', 'left')
            ->where('event_comments.event_id', $event['event_id'])
            ->orderBy('event_comments.id')
            ->get()
            ->getResultArray();

        $userId      = (int) auth()->user()->id;
        $friends     = $this->photoOwnersVisibleTo($userId);
        $isOrganizer = $userId === (int) $event['user_id'];

        return array_map(fn (array $row): array => [
            'id'         => (int) $row['comment_id'],
            'author'     => $this->personData($row, in_array((int) $row['id'], $friends, true)),
            'body'       => $row['body'],
            'created_at' => $row['created_at'],
            'can_delete' => $isOrganizer || (int) $row['id'] === $userId,
        ], $rows);
    }
}
