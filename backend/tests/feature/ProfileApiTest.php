<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\UserModel;
use App\Models\WelcomeProfileModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class ProfileApiTest extends ApiTestCase
{
    public function testProfileRequiresAnAuthenticatedMember(): void
    {
        $user = $this->createUser();

        $this->get("api/profiles/{$user->id}")->assertStatus(401);
    }

    public function testProfileUpdatesPersonalDetailsAndVisibility(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $result = $this->withBodyFormat('json')->put('api/profile', [
            'first_name'           => 'Ana María',
            'last_name'            => 'García',
            'birthdate'            => '1990-01-01',
            'bio'                  => 'Me gusta viajar.',
            'status'               => '¡Hola!',
            'birthdate_visibility' => 'private',
            'bio_visibility'       => 'friends',
            'status_visibility'    => 'public',
            'avatar_visibility'    => 'friends',
        ]);

        $result->assertOK();
        $profile = $this->json($result)['profile'];
        $this->assertSame('Me gusta viajar.', $profile['bio']);
        $this->assertSame('¡Hola!', $profile['status']);
        $this->assertSame('private', $profile['visibility']['birthdate']);
        $this->assertSame('friends', $profile['visibility']['bio']);
    }

    public function testProfileAvatarUrlsAreRelativeToTheCurrentDomain(): void
    {
        $user = $this->createUser();
        model(UserModel::class)->update((int) $user->id, ['avatar' => str_repeat('a', 32) . '.png']);
        $this->actingAs($user);

        $profile = $this->json($this->get("api/profiles/{$user->id}"))['profile'];
        $this->assertSame("/api/profiles/{$user->id}/avatar", $profile['avatar_url']);

        $welcome     = model(WelcomeProfileModel::class)->first();
        $welcomeUser = auth()->getProvider()->findById((int) $welcome['user_id']);
        $this->actingAs($welcomeUser);

        $welcomeProfile = $this->json($this->get("api/profiles/{$welcomeUser->id}"))['profile'];
        $this->assertSame($welcome['avatar'], $welcomeProfile['avatar_url']);
    }

    public function testProfileRejectsUnknownVisibility(): void
    {
        $this->actingAs($this->createUser());

        $result = $this->withBodyFormat('json')->put('api/profile', [
            'first_name'           => 'Ana',
            'last_name'            => 'Pruebas',
            'birthdate'            => '1990-01-01',
            'bio'                  => '',
            'status'               => '',
            'birthdate_visibility' => 'anyone',
            'bio_visibility'       => 'public',
            'status_visibility'    => 'public',
            'avatar_visibility'    => 'public',
        ]);

        $result->assertStatus(422);
        $this->assertArrayHasKey('birthdate_visibility', $this->json($result)['errors']);
    }

    public function testPrivateAndFriendsOnlyDetailsAreFilteredForVisitors(): void
    {
        $owner = $this->createUser();
        $this->actingAs($owner);
        $this->withBodyFormat('json')->put('api/profile', [
            'first_name'           => 'Ana',
            'last_name'            => 'Pruebas',
            'birthdate'            => '1990-01-01',
            'bio'                  => 'Solo amigos',
            'status'               => 'Solo yo',
            'birthdate_visibility' => 'friends',
            'bio_visibility'       => 'friends',
            'status_visibility'    => 'private',
            'avatar_visibility'    => 'public',
        ])->assertOK();
        $other = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($other);

        $memberProfile = $this->json($this->get("api/profiles/{$owner->id}"))['profile'];
        $this->assertNull($memberProfile['birthdate']);
        $this->assertNull($memberProfile['bio']);
        $this->assertNull($memberProfile['status']);

        $friend = $this->createUser('carlos@example.com', 'Carlos');
        model(FriendshipModel::class)->befriend((int) $owner->id, (int) $friend->id);
        $this->actingAs($friend);

        $friendProfile = $this->json($this->get("api/profiles/{$owner->id}"))['profile'];
        $this->assertSame('1990-01-01', $friendProfile['birthdate']);
        $this->assertSame('Solo amigos', $friendProfile['bio']);
        $this->assertNull($friendProfile['status']);
        $this->assertArrayNotHasKey('visibility', $friendProfile);
    }

    public function testDashboardSummarizesOwnInvitationsAndFriends(): void
    {
        $user   = $this->createUser();
        $friend = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $user->id, (int) $friend->id);
        $this->actingAs($user);

        $this->postJson('api/invitations', ['email' => 'carlos@example.com'])->assertStatus(201);
        $summary = $this->json($this->get('api/dashboard'));

        $this->assertSame(1, $summary['friends_count']);
        $this->assertSame(1, $summary['invitations_sent']);
        $this->assertSame(0, $summary['invitations_accepted']);
    }
}
