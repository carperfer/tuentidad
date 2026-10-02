<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\InvitationModel;
use App\Models\WelcomeProfileModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class WelcomeProfileApiTest extends ApiTestCase
{
    public function testListsTheTwoWelcomeProfiles(): void
    {
        $result = $this->get('api/welcome-profiles');

        $result->assertOK();
        $profiles = $this->json($result)['profiles'];
        $this->assertCount(2, $profiles);
        $this->assertSame(['id', 'first_name', 'last_name', 'bio', 'avatar'], array_keys($profiles[0]));
    }

    public function testFriendRequestSendsInvitationWithConsent(): void
    {
        $result = $this->request(1, ['email' => ' Marta@Example.com ', 'accept_privacy' => true]);

        $result->assertOK();
        $this->assertCount(1, $this->email->sent);
        $this->assertSame(['marta@example.com'], $this->email->sent[0]['to']);
        $this->assertStringContainsString('ha aceptado tu solicitud de amistad', $this->email->sent[0]['subject']);

        $invitation = model(InvitationModel::class)->first();
        $this->assertSame($this->welcomeUserId(1), (int) $invitation['inviter_id']);
        $this->assertNotNull($invitation['consented_at']);
    }

    public function testRegisteringFromWelcomeRequestCreatesFriendship(): void
    {
        $this->request(1, ['email' => 'marta@example.com', 'accept_privacy' => true]);
        $token = $this->tokenFromEmail('registro');

        $this->assertTrue($this->json($this->get("api/invitations/{$token}"))['welcome']);

        $result = $this->postJson('api/auth/register', [
            'token'            => $token,
            'first_name'       => 'Marta',
            'last_name'        => 'Gil',
            'birthdate'        => '1999-06-15',
            'password'         => 'Bosque.Lluvia.Rojo.4',
            'password_confirm' => 'Bosque.Lluvia.Rojo.4',
            'accept_terms'     => true,
        ]);

        $result->assertStatus(201);
        $userId     = $this->json($result)['user']['id'];
        $friendship = model(FriendshipModel::class);
        $this->assertTrue($friendship->areFriends($userId, $this->welcomeUserId(1)));
        $this->assertTrue($friendship->areFriends($this->welcomeUserId(1), $userId));
    }

    public function testRegularInvitationDoesNotCreateFriendship(): void
    {
        $ana = $this->createUser();
        $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com']);
        $token = $this->tokenFromEmail('registro');
        auth()->logout();

        $this->assertFalse($this->json($this->get("api/invitations/{$token}"))['welcome']);
    }

    public function testRequiresPrivacyAcceptance(): void
    {
        $result = $this->request(1, ['email' => 'marta@example.com']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('accept_privacy', $this->json($result)['errors']);
        $this->assertSame([], $this->email->sent);
    }

    public function testHoneypotPretendsSuccessWithoutSending(): void
    {
        $result = $this->request(1, ['email' => 'bot@example.com', 'accept_privacy' => true, 'website' => 'http://spam']);

        $result->assertOK();
        $this->assertSame([], $this->email->sent);
        $this->assertSame(0, model(InvitationModel::class)->countAllResults());
    }

    public function testRegisteredEmailGetsReminderAndSameAnswer(): void
    {
        $this->createUser('ana@example.com');
        $new = $this->json($this->request(1, ['email' => 'nueva@example.com', 'accept_privacy' => true]));

        $result = $this->request(1, ['email' => 'ana@example.com', 'accept_privacy' => true]);

        $result->assertOK();
        $this->assertSame($new, $this->json($result));
        $this->assertSame('Ya tienes cuenta en tuentidad', $this->email->sent[1]['subject']);
        $this->assertSame(1, model(InvitationModel::class)->countAllResults());
    }

    public function testUnknownProfile(): void
    {
        $this->request(99, ['email' => 'marta@example.com', 'accept_privacy' => true])->assertStatus(404);
    }

    public function testIsThrottledPerIp(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->request(1, ['email' => "persona{$i}@example.com", 'accept_privacy' => true])->assertOK();
        }

        $this->request(1, ['email' => 'otra@example.com', 'accept_privacy' => true])->assertStatus(429);
    }

    public function testPurgesStaleInvitations(): void
    {
        $model = model(InvitationModel::class);
        $model->insert([
            'inviter_id' => $this->welcomeUserId(1),
            'email'      => 'antigua@example.com',
            'token_hash' => str_repeat('a', 64),
            'expires_at' => date('Y-m-d H:i:s', time() - 31 * DAY),
        ]);

        $this->request(2, ['email' => 'marta@example.com', 'accept_privacy' => true]);

        $this->assertSame(['marta@example.com'], array_column($model->findAll(), 'email'));
    }

    public function testWelcomeProfilesCannotLogIn(): void
    {
        $this->assertNull(auth()->getProvider()->findById($this->welcomeUserId(1))->email);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function request(int $profileId, array $data)
    {
        return $this->postJson("api/welcome-profiles/{$profileId}/friend-requests", $data);
    }

    private function welcomeUserId(int $profileId): int
    {
        return (int) model(WelcomeProfileModel::class)->find($profileId)['user_id'];
    }
}
