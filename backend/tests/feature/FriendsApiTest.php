<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class FriendsApiTest extends ApiTestCase
{
    public function testFriendEndpointsRequireAuthentication(): void
    {
        $this->get('api/friends')->assertStatus(401);
        $this->get('api/friend-requests')->assertStatus(401);
    }

    public function testRequestCanBeSentAndAcceptedAsMutualFriendship(): void
    {
        $sender    = $this->createUser();
        $recipient = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($sender);

        $this->postJson('api/friend-requests', ['user_id' => $recipient->id])->assertStatus(201);
        $requests = $this->json($this->get('api/friend-requests'));
        $this->assertCount(1, $requests['outgoing']);
        $requestId = (int) $requests['outgoing'][0]['id'];

        $this->actingAs($recipient);
        $this->postJson("api/friend-requests/{$requestId}/accept")->assertOK();

        $this->assertTrue(model(FriendshipModel::class)->areFriends((int) $sender->id, (int) $recipient->id));
        $this->assertTrue(model(FriendshipModel::class)->areFriends((int) $recipient->id, (int) $sender->id));
        $this->assertCount(0, $this->json($this->get('api/friend-requests'))['incoming']);
    }

    public function testUsersCanRejectOrCancelOnlyTheirOwnRequests(): void
    {
        $sender    = $this->createUser();
        $recipient = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($sender);
        $this->postJson('api/friend-requests', ['user_id' => $recipient->id])->assertStatus(201);
        $requestId = (int) $this->json($this->get('api/friend-requests'))['outgoing'][0]['id'];

        $this->postJson("api/friend-requests/{$requestId}/accept")->assertStatus(404);
        $this->delete("api/friend-requests/{$requestId}")->assertOK();
        $this->assertCount(0, $this->json($this->get('api/friend-requests'))['outgoing']);
    }

    public function testSearchDoesNotReturnTheCurrentUserAndReportsRelationship(): void
    {
        $user   = $this->createUser();
        $friend = $this->createUser('bea@example.com', 'Beatriz');
        model(FriendshipModel::class)->befriend((int) $user->id, (int) $friend->id);
        $this->actingAs($user);

        $results = $this->json($this->get('api/friends/search?q=Bea'))['users'];

        $this->assertCount(1, $results);
        $this->assertSame((int) $friend->id, $results[0]['id']);
        $this->assertSame('friend', $results[0]['relationship']);
        $this->assertArrayNotHasKey('email', $results[0]);
    }

    public function testRemovingFriendshipDeletesBothDirections(): void
    {
        $user   = $this->createUser();
        $friend = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $user->id, (int) $friend->id);
        $this->actingAs($user);

        $this->delete("api/friends/{$friend->id}")->assertOK();

        $this->assertFalse(model(FriendshipModel::class)->areFriends((int) $user->id, (int) $friend->id));
        $this->assertFalse(model(FriendshipModel::class)->areFriends((int) $friend->id, (int) $user->id));
    }

    public function testActivityShowsOnlyVisibleStatusesFromConfirmedFriends(): void
    {
        $user       = $this->createUser();
        $friend     = $this->createUser('bea@example.com', 'Bea');
        $private    = $this->createUser('carlos@example.com', 'Carlos');
        $stranger   = $this->createUser('dani@example.com', 'Dani');
        $friendship = model(FriendshipModel::class);
        $friendship->befriend((int) $user->id, (int) $friend->id);
        $friendship->befriend((int) $user->id, (int) $private->id);
        $users = model(UserModel::class);
        $users->update((int) $friend->id, ['profile_status' => '¡Hola!', 'status_visibility' => 'friends', 'profile_status_updated_at' => '2026-10-23 10:00:00']);
        $users->update((int) $private->id, ['profile_status' => 'Solo para mí', 'status_visibility' => 'private', 'profile_status_updated_at' => '2026-10-23 11:00:00']);
        $users->update((int) $stranger->id, ['profile_status' => 'No somos amigos', 'status_visibility' => 'public', 'profile_status_updated_at' => '2026-10-23 12:00:00']);
        $this->actingAs($user);

        $activity = $this->json($this->get('api/friends/activity'))['activity'];

        $this->assertCount(1, $activity);
        $this->assertSame('¡Hola!', $activity[0]['status']);
        $this->assertSame((int) $friend->id, (int) $activity[0]['id']);
    }
}
