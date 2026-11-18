<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\InvitationModel;
use App\Models\NotificationModel;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * Centro de avisos unificado y emails de resumen.
 *
 * @internal
 */
final class NotificationCenterTest extends ApiTestCase
{
    private const TOKEN = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdefabcd';

    public function testAcceptingARequestNotifiesTheSender(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($ana);
        $this->postJson('api/friend-requests', ['user_id' => $bea->id])->assertStatus(201);

        $this->actingAs($bea);
        $requestId = $this->json($this->get('api/friend-requests'))['incoming'][0]['id'];
        $this->post("api/friend-requests/{$requestId}/accept")->assertOK();

        $this->actingAs($ana);
        $notifications = $this->json($this->get('api/notifications'))['notifications'];
        $this->assertSame(['friend_accepted'], array_column($notifications, 'type'));
        $this->assertSame((int) $bea->id, $notifications[0]['actor']['id']);
    }

    public function testRegisteringWithAnInvitationNotifiesTheInviter(): void
    {
        $ana   = $this->createUser('ana@example.com', 'Ana');
        $token = str_repeat('c', 64);
        model(InvitationModel::class)->insert([
            'inviter_id' => $ana->id,
            'email'      => 'bea@example.com',
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $this->postJson('api/auth/register', [
            'token'            => $token,
            'first_name'       => 'Bea',
            'last_name'        => 'Ruiz',
            'birthdate'        => '1999-04-01',
            'password'         => 'Montaña-Roja-42',
            'password_confirm' => 'Montaña-Roja-42',
            'accept_terms'     => true,
        ])->assertStatus(201);

        $this->seeInDatabase('notifications', ['user_id' => $ana->id, 'type' => 'invitation_accepted']);
    }

    public function testNotificationsArePagedFilteredAndMarkedOneByOne(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        $notifications = model(NotificationModel::class);
        for ($i = 0; $i < 22; $i++) {
            $notifications->notify((int) $ana->id, (int) $bea->id, 'friend_accepted');
        }
        $notifications->notify((int) $ana->id, (int) $bea->id, 'invitation_accepted');
        $this->actingAs($ana);

        $first = $this->json($this->get('api/notifications'));
        $this->assertCount(20, $first['notifications']);
        $this->assertSame('invitation_accepted', $first['notifications'][0]['type']);
        $second = $this->json($this->get("api/notifications?before={$first['next_before']}"));
        $this->assertCount(3, $second['notifications']);
        $this->assertNull($second['next_before']);

        $this->assertSame([], $this->json($this->get('api/notifications?group=photos'))['notifications']);
        $this->get('api/notifications?group=otros')->assertStatus(422);

        $id     = $first['notifications'][0]['id'];
        $unread = $this->json($this->post("api/notifications/{$id}/read"))['unread'];
        $this->assertSame(22, $unread['total']);
        $this->assertSame(0, $unread['invitation_accepted']);
    }

    public function testNobodyElseCanMarkSomeoneElsesNotification(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        model(NotificationModel::class)->notify((int) $ana->id, (int) $bea->id, 'friend_accepted');
        $id = (int) model(NotificationModel::class)->getInsertID();

        $this->actingAs($bea);
        $this->post("api/notifications/{$id}/read")->assertOK();

        $this->seeInDatabase('notifications', ['id' => $id, 'read_at' => null]);
    }

    public function testDigestSettingIsValidatedAndShownOnlyToTheOwner(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $this->actingAs($ana);

        $this->withBodyFormat('json')->put('api/profile/settings', ['digest' => 'hourly'])->assertStatus(422);
        $profile = $this->json($this->withBodyFormat('json')->put('api/profile/settings', ['digest' => 'weekly']))['profile'];

        $this->assertSame('weekly', $profile['digest']);
        $this->assertNotNull(model(UserModel::class)->find($ana->id)->digest_sent_at);
    }

    public function testDigestsAreSentOnlyWhenDueAndThereIsSomethingNew(): void
    {
        config('Tuentidad')->deployToken = self::TOKEN;
        $ana  = $this->createUser('ana@example.com', 'Ana');
        $bea  = $this->createUser('bea@example.com', 'Bea');
        $eva  = $this->createUser('eva@example.com', 'Eva');
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $users     = model(UserModel::class);
        $users->update((int) $ana->id, ['digest' => 'daily', 'digest_sent_at' => $yesterday]);
        // Bea lo tiene activado pero no tiene nada nuevo; Eva no lo tiene activado
        $users->update((int) $bea->id, ['digest' => 'daily', 'digest_sent_at' => $yesterday]);
        model(FriendshipModel::class)->befriend((int) $ana->id, (int) $eva->id);
        model(NotificationModel::class)->notify((int) $ana->id, (int) $eva->id, 'friend_accepted');
        model(NotificationModel::class)->notify((int) $eva->id, (int) $ana->id, 'friend_accepted');
        $this->actingAs($eva);
        $this->postJson("api/chat/{$ana->id}/messages", ['body' => 'Hola'])->assertStatus(201);
        $this->sendCsrf = false;

        $result = $this->json($this->withHeaders(['Authorization' => 'Bearer ' . self::TOKEN])->post('api/deploy/digests'));

        $this->assertSame(['users' => 2, 'sent' => 1, 'failed' => 0], $result);
        $email = end($this->email->sent);
        $this->assertSame('Novedades en tuentidad', $email['subject']);
        $this->assertStringContainsString('ana@example.com', implode(',', (array) $email['to']));
        $this->assertStringContainsString('Eva Pruebas ha aceptado tu solicitud de amistad', $email['body']);
        $this->assertStringContainsString('1 mensaje de chat sin leer', $email['body']);

        // Ya enviado hoy: no se repite
        $this->assertSame(0, $this->json($this->withHeaders(['Authorization' => 'Bearer ' . self::TOKEN])->post('api/deploy/digests'))['sent']);
    }
}
