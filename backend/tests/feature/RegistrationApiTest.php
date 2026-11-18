<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\InvitationModel;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class RegistrationApiTest extends ApiTestCase
{
    private const TOKEN = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2';

    private int $inviterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inviterId = (int) $this->createUser()->id;
        $this->invite();
    }

    public function testRegistersWithValidInvitation(): void
    {
        $result = $this->postJson('api/auth/register', $this->validData());

        $result->assertStatus(201);
        $this->assertSame('bea@example.com', $this->json($result)['user']['email']);
        $this->assertTrue(auth()->loggedIn(), 'Queda con la sesión iniciada');

        $user = auth()->getProvider()->findByCredentials(['email' => 'bea@example.com']);
        $this->assertTrue($user->isActivated());
        $this->assertSame('2000-02-29', $user->birthdate);
        $this->assertTrue($user->inGroup('user'));

        $invitation = model(InvitationModel::class)->first();
        $this->assertNotNull($invitation['accepted_at']);
        $this->assertSame((string) $user->id, (string) $invitation['user_id']);
    }

    public function testRegisteringMakesTheInviterAFriend(): void
    {
        $this->postJson('api/auth/register', $this->validData())->assertStatus(201);

        $user = auth()->getProvider()->findByCredentials(['email' => 'bea@example.com']);
        $this->assertTrue(model(FriendshipModel::class)->areFriends((int) $user->id, $this->inviterId));
        $this->assertTrue(model(FriendshipModel::class)->areFriends($this->inviterId, (int) $user->id));
    }

    public function testNewAccountsStartWithAnEmptyAlbum(): void
    {
        $user = $this->json($this->postJson('api/auth/register', $this->validData()))['user'];

        $albums = $this->json($this->get("api/users/{$user['id']}/albums"))['albums'];
        $this->assertSame([['Mis fotos', 0]], array_map(static fn (array $album): array => [$album['title'], $album['photo_count']], $albums));
    }

    public function testRegistersWhenTheInviterNoLongerExists(): void
    {
        model(UserModel::class)->delete($this->inviterId, true);

        $this->postJson('api/auth/register', $this->validData())->assertStatus(201);

        $this->dontSeeInDatabase('friendships', ['friend_id' => $this->inviterId]);
    }

    public function testInvitationCanOnlyBeUsedOnce(): void
    {
        $this->postJson('api/auth/register', $this->validData())->assertStatus(201);
        auth()->logout();

        $this->postJson('api/auth/register', $this->validData())->assertStatus(404);
    }

    public function testExpiredInvitationIsRejected(): void
    {
        model(InvitationModel::class)->where('id >', 0)->set('expires_at', date('Y-m-d H:i:s', time() - 1))->update();

        $this->postJson('api/auth/register', $this->validData())->assertStatus(404);
    }

    public function testValidatesFields(): void
    {
        $result = $this->postJson('api/auth/register', [
            'token'            => self::TOKEN,
            'first_name'       => '',
            'last_name'        => 'Ruiz',
            'birthdate'        => '31/12/2000',
            'password'         => 'corta',
            'password_confirm' => 'otra',
        ]);

        $result->assertStatus(422);
        $errors = $this->json($result)['errors'];

        foreach (['first_name', 'birthdate', 'password', 'password_confirm', 'accept_terms'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    public function testRejectsUnderMinimumAge(): void
    {
        $birthdate = date('Y-m-d', strtotime('-14 years +1 day'));

        $result = $this->postJson('api/auth/register', ['birthdate' => $birthdate] + $this->validData());

        $result->assertStatus(422);
        $this->assertStringContainsString('14 años', $this->json($result)['errors']['birthdate']);
    }

    public function testAcceptsExactlyMinimumAge(): void
    {
        $birthdate = date('Y-m-d', strtotime('-14 years'));

        $this->postJson('api/auth/register', ['birthdate' => $birthdate] + $this->validData())->assertStatus(201);
    }

    public function testRejectsPasswordWithPersonalData(): void
    {
        $result = $this->postJson('api/auth/register', ['password' => 'bea-ruiz-2000', 'password_confirm' => 'bea-ruiz-2000'] + $this->validData());

        $result->assertStatus(422);
        $this->assertArrayHasKey('password', $this->json($result)['errors']);
    }

    private function invite(): void
    {
        model(InvitationModel::class)->insert([
            'inviter_id' => $this->inviterId,
            'email'      => 'bea@example.com',
            'token_hash' => hash('sha256', self::TOKEN),
            'expires_at' => date('Y-m-d H:i:s', time() + DAY),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(): array
    {
        return [
            'token'            => self::TOKEN,
            'first_name'       => 'Bea',
            'last_name'        => 'Ruiz',
            'birthdate'        => '2000-02-29',
            'password'         => 'Montaña-Roja-42',
            'password_confirm' => 'Montaña-Roja-42',
            'accept_terms'     => true,
        ];
    }
}
