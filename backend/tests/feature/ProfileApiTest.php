<?php

namespace Tests\Feature;

use App\Database\Migrations\StripAvatarMetadata;
use App\Models\FriendshipModel;
use App\Models\UserModel;
use App\Models\WelcomeProfileModel;
use Config\Database;
use Tests\Support\ApiTestCase;
use Tests\Support\TestImages;

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

    public function testProfileReportsTheRelationshipWithTheViewer(): void
    {
        $ana = $this->createUser();
        $bea = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($ana);

        $this->assertSame('self', $this->json($this->get("api/profiles/{$ana->id}"))['profile']['relationship']);
        $this->assertSame('none', $this->json($this->get("api/profiles/{$bea->id}"))['profile']['relationship']);

        $this->postJson('api/friend-requests', ['user_id' => $bea->id])->assertStatus(201);
        $outgoing = $this->json($this->get("api/profiles/{$bea->id}"))['profile'];
        $this->assertSame('outgoing', $outgoing['relationship']);
        $this->assertIsInt($outgoing['request_id']);

        $this->actingAs($bea);
        $incoming = $this->json($this->get("api/profiles/{$ana->id}"))['profile'];
        $this->assertSame('incoming', $incoming['relationship']);
        $this->assertSame($outgoing['request_id'], $incoming['request_id']);
    }

    public function testStatusCanBeChangedOnItsOwn(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $result = $this->withBodyFormat('json')->put('api/profile/status', ['status' => '  De exámenes  ']);

        $result->assertOK();
        $profile = $this->json($result)['profile'];
        $this->assertSame('De exámenes', $profile['status']);
        $this->assertNotNull($profile['status_updated_at']);
        $this->assertSame('1990-01-01', $profile['birthdate']);

        $this->withBodyFormat('json')->put('api/profile/status', ['status' => str_repeat('a', 161)])->assertStatus(422);
    }

    public function testMigrationStripsMetadataFromExistingAvatars(): void
    {
        $user      = $this->createUser();
        $directory = WRITEPATH . 'uploads/avatars';
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        $old = bin2hex(random_bytes(16)) . '.jpg';
        TestImages::jpeg("{$directory}/{$old}", 900, 300, 6);
        model(UserModel::class)->update((int) $user->id, ['avatar' => $old]);

        (new StripAvatarMetadata(Database::forge()))->up();

        $new = (string) model(UserModel::class)->find($user->id)->avatar;
        $this->assertNotSame($old, $new);
        $this->assertFileDoesNotExist("{$directory}/{$old}");
        $this->assertStringNotContainsString('Exif', (string) file_get_contents("{$directory}/{$new}"));
        $this->assertSame([200, 600], array_slice((array) getimagesize("{$directory}/{$new}"), 0, 2));
        unlink("{$directory}/{$new}");
    }
}
