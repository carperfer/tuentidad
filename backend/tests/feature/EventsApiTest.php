<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class EventsApiTest extends ApiTestCase
{
    private User $ana;
    private User $bea;
    private User $carla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana   = $this->createUser('ana@example.com', 'Ana');
        $this->bea   = $this->createUser('bea@example.com', 'Bea');
        $this->carla = $this->createUser('carla@example.com', 'Carla');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $this->bea->id);
    }

    public function testEventsRequireAuthentication(): void
    {
        $this->get('api/events')->assertStatus(401);
        $this->get('api/calendar')->assertStatus(401);
    }

    public function testOrganizerCreatesAnEventAndInvitesFriends(): void
    {
        $this->actingAs($this->ana);

        $result = $this->postJson('api/events', $this->eventData(['invite' => [$this->bea->id]]));

        $result->assertStatus(201);
        $event = $this->json($result)['event'];
        $this->assertTrue($event['is_organizer']);
        $this->assertSame('organizer', $event['my_status']);
        $this->assertSame(date('Y-m-d', strtotime('+3 days')) . ' 18:30:00', $event['starts_at']);
        $this->assertSame([['Bea', 'pending']], array_map(static fn (array $invitee): array => [$invitee['person']['first_name'], $invitee['status']], $event['invitees']));
        $this->assertSame(1, $event['counts']['yes']);
        $this->seeInDatabase('notifications', ['user_id' => $this->bea->id, 'type' => 'event_invite', 'event_id' => $event['id']]);
    }

    public function testOnlyFriendsCanBeInvited(): void
    {
        $this->actingAs($this->ana);

        $result = $this->postJson('api/events', $this->eventData(['invite' => [$this->carla->id]]));

        $result->assertStatus(422);
        $this->assertArrayHasKey('invite', $this->json($result)['errors']);
        $this->dontSeeInDatabase('events', ['user_id' => $this->ana->id]);
    }

    public function testValidatesTitleAndDate(): void
    {
        $this->actingAs($this->ana);

        $errors = $this->json($this->postJson('api/events', $this->eventData(['title' => '', 'starts_at' => 'mañana'])))['errors'];
        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayHasKey('starts_at', $errors);

        $past = $this->postJson('api/events', $this->eventData(['starts_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime('-2 days'))]));
        $past->assertStatus(422);
        $this->assertSame('El evento tiene que ser en el futuro.', $this->json($past)['errors']['starts_at']);
    }

    public function testOnlyTheOrganizerAndInviteesCanSeeTheEvent(): void
    {
        $eventId = $this->createEvent([$this->bea->id]);

        $this->actingAs($this->bea);
        $this->get("api/events/{$eventId}")->assertOK();
        $this->assertSame([$eventId], array_column($this->json($this->get('api/events'))['upcoming'], 'id'));

        $this->actingAs($this->carla);
        $this->get("api/events/{$eventId}")->assertStatus(404);
        $this->postJson("api/events/{$eventId}/comments", ['body' => 'Hola'])->assertStatus(404);
        $this->assertSame([], $this->json($this->get('api/events'))['upcoming']);
    }

    public function testInviteesRespondAndOnlyTheOrganizerCanChangeTheEvent(): void
    {
        $eventId = $this->createEvent([$this->bea->id]);

        $this->actingAs($this->bea);
        $this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'quizás'])->assertStatus(422);
        $event = $this->json($this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'maybe']))['event'];
        $this->assertSame('maybe', $event['my_status']);
        $this->assertSame(1, $event['counts']['maybe']);
        $this->withBodyFormat('json')->put("api/events/{$eventId}", $this->eventData(['title' => 'Mía']))->assertStatus(404);
        $this->delete("api/events/{$eventId}")->assertStatus(404);

        $this->actingAs($this->ana);
        $this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'yes'])->assertStatus(409);
        $updated = $this->json($this->withBodyFormat('json')->put("api/events/{$eventId}", $this->eventData(['title' => 'Cumple de Ana'])))['event'];
        $this->assertSame('Cumple de Ana', $updated['title']);
        $this->delete("api/events/{$eventId}")->assertOK();
        $this->dontSeeInDatabase('events', ['id' => $eventId]);
        $this->dontSeeInDatabase('notifications', ['event_id' => $eventId]);
    }

    public function testInvitingMoreFriendsLater(): void
    {
        $dani = $this->createUser('dani@example.com', 'Dani');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $dani->id);
        $eventId = $this->createEvent([$this->bea->id]);
        $this->actingAs($this->ana);

        $invitable = $this->json($this->get("api/events/invitable?event={$eventId}"))['people'];
        $this->assertSame(['Dani'], array_column($invitable, 'first_name'));

        $event = $this->json($this->postJson("api/events/{$eventId}/invitations", ['user_ids' => [$dani->id]]))['event'];
        $this->assertCount(2, $event['invitees']);
        $this->postJson("api/events/{$eventId}/invitations", ['user_ids' => [$this->carla->id]])->assertStatus(422);
    }

    public function testCommentsNotifyTheOrganizerAndAttendees(): void
    {
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $eva->id);
        $eventId = $this->createEvent([$this->bea->id, $eva->id]);
        $this->actingAs($this->bea);
        $this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'yes'])->assertOK();

        // Eva no ha respondido: no recibe el aviso del comentario
        $this->actingAs($eva);
        $comment = $this->json($this->postJson("api/events/{$eventId}/comments", ['body' => '¿Llevo algo?']))['comments'][0];
        $this->assertTrue($comment['can_delete']);
        $this->seeInDatabase('notifications', ['user_id' => $this->ana->id, 'type' => 'event_comment']);
        $this->seeInDatabase('notifications', ['user_id' => $this->bea->id, 'type' => 'event_comment']);

        $this->actingAs($this->bea);
        $this->delete("api/event-comments/{$comment['id']}")->assertStatus(404);
        $this->actingAs($this->ana);
        $this->delete("api/event-comments/{$comment['id']}")->assertOK();
        $this->dontSeeInDatabase('notifications', ['type' => 'event_comment']);
    }

    public function testCalendarShowsTheWeeksEventsAndVisibleBirthdays(): void
    {
        $eventId = $this->createEvent([$this->bea->id]);
        $soon    = date('Y-m-d', strtotime('+2 days'));
        model(UserModel::class)->update((int) $this->bea->id, ['birthdate' => '1995-' . substr($soon, 5), 'birthdate_visibility' => 'friends']);
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $eva->id);
        model(UserModel::class)->update((int) $eva->id, ['birthdate' => '1995-' . substr($soon, 5), 'birthdate_visibility' => 'private']);

        $this->actingAs($this->ana);
        $calendar = $this->json($this->get('api/calendar'));
        $this->assertSame([$eventId], array_column($calendar['events'], 'id'));
        $this->assertSame([['Bea', $soon]], array_map(static fn (array $birthday): array => [$birthday['person']['first_name'], $birthday['date']], $calendar['birthdays']));

        // Quien dice que no va deja de verlo en su calendario
        $this->actingAs($this->bea);
        $this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'no'])->assertOK();
        $this->assertSame([], $this->json($this->get('api/calendar'))['events']);
    }

    public function testRemindersGoToAttendeesOnceADayBefore(): void
    {
        config('Tuentidad')->deployToken = str_repeat('a', 64);
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $eva->id);
        $eventId = $this->createEvent([$this->bea->id, $eva->id], gmdate('Y-m-d\TH:i:s\Z', strtotime('+20 hours')));
        $this->actingAs($this->bea);
        $this->withBodyFormat('json')->put("api/events/{$eventId}/rsvp", ['status' => 'maybe'])->assertOK();
        $this->createEvent([$this->bea->id], gmdate('Y-m-d\TH:i:s\Z', strtotime('+3 days')));
        $this->sendCsrf = false;

        $this->withHeaders(['Authorization' => 'Bearer ' . str_repeat('b', 64)])->post('api/deploy/reminders')->assertStatus(401);
        $result = $this->json($this->withHeaders(['Authorization' => 'Bearer ' . str_repeat('a', 64)])->post('api/deploy/reminders'));

        $this->assertSame(['events' => 1, 'reminders' => 1, 'emails_failed' => 0], $result);
        $this->seeInDatabase('notifications', ['user_id' => $this->bea->id, 'type' => 'event_reminder', 'event_id' => $eventId]);
        $this->dontSeeInDatabase('notifications', ['user_id' => $eva->id, 'type' => 'event_reminder']);
        $last = end($this->email->sent);
        $this->assertSame('Recordatorio: Cumpleaños de Ana', $last['subject']);
        $this->assertStringContainsString('bea@example.com', implode(',', (array) $last['to']));

        $this->assertSame(0, $this->json($this->withHeaders(['Authorization' => 'Bearer ' . str_repeat('a', 64)])->post('api/deploy/reminders'))['events']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function eventData(array $overrides = []): array
    {
        return [
            'title'       => 'Cumpleaños de Ana',
            'place'       => 'Bar La Esquina',
            'description' => 'Trae ganas de bailar',
            'starts_at'   => date('Y-m-d', strtotime('+3 days')) . 'T18:30:00Z',
            ...$overrides,
        ];
    }

    /**
     * @param list<int|string> $invite
     */
    private function createEvent(array $invite, ?string $startsAt = null): int
    {
        $this->actingAs($this->ana);
        $data   = $this->eventData(['invite' => $invite] + ($startsAt === null ? [] : ['starts_at' => $startsAt]));
        $result = $this->postJson('api/events', $data);
        $result->assertStatus(201);

        return $this->json($result)['event']['id'];
    }
}
