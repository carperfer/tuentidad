<?php

namespace Tests\Feature;

use App\Models\AlbumModel;
use App\Models\FriendshipModel;
use App\Models\PhotoModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class NotificationApiTest extends ApiTestCase
{
    public function testNotificationsRequireAuthentication(): void
    {
        $this->get('api/notifications')->assertStatus(401);
        $this->get('api/notifications/unread')->assertStatus(401);
    }

    public function testFriendRequestNotificationLastsWhileTheRequestExists(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        $this->actingAs($ana);
        $this->postJson('api/friend-requests', ['user_id' => $bea->id])->assertStatus(201);

        $this->actingAs($bea);
        $result = $this->json($this->get('api/notifications'));
        $this->assertSame(1, $result['unread']['total']);
        $this->assertSame(1, $result['unread']['friend_request']);
        $this->assertSame('friend_request', $result['notifications'][0]['type']);
        $this->assertSame((int) $ana->id, $result['notifications'][0]['actor']['id']);
        $this->assertNull($result['notifications'][0]['photo']);

        $requestId = $this->json($this->get('api/friend-requests'))['incoming'][0]['id'];
        $this->post("api/friend-requests/{$requestId}/accept")->assertOK();
        $this->assertSame([], $this->json($this->get('api/notifications'))['notifications']);
    }

    public function testMarkingAsReadAndViewingThePhoto(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $ana->id, (int) $bea->id);
        $first  = $this->addPhoto((int) $ana->id);
        $second = $this->addPhoto((int) $ana->id);

        $this->actingAs($bea);
        $this->postJson("api/photos/{$first}/comments", ['body' => 'Uno'])->assertStatus(201);
        $this->postJson("api/photos/{$second}/comments", ['body' => 'Dos'])->assertStatus(201);

        $this->actingAs($ana);
        $this->assertSame(2, $this->json($this->get('api/notifications/unread'))['unread']['photo_comment']);
        $notification = $this->json($this->get('api/notifications'))['notifications'][0];
        $this->assertSame("/api/photos/{$second}/thumb", $notification['photo']['thumb_url']);
        $this->assertFalse($notification['read']);

        $this->get("api/photos/{$first}")->assertOK();
        $this->assertSame(1, $this->json($this->get('api/notifications/unread'))['unread']['total']);

        $this->assertSame(0, $this->json($this->post('api/notifications/read'))['unread']['total']);
        $this->assertTrue($this->json($this->get('api/notifications'))['notifications'][0]['read']);
    }

    public function testPhotoNotificationsDisappearWhenThePhotoIsNoLongerVisible(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $ana->id, (int) $bea->id);
        $photoId = $this->addPhoto((int) $ana->id);

        $this->actingAs($ana);
        $this->postJson("api/photos/{$photoId}/tags", ['user_id' => $bea->id, 'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50])->assertStatus(201);
        $this->postJson("api/photos/{$photoId}/comments", ['body' => 'Mira'])->assertStatus(201);

        $this->actingAs($bea);
        $this->assertSame(2, $this->json($this->get('api/notifications/unread'))['unread']['total']);

        // Al romper la amistad se quita la etiqueta y el aviso del comentario deja de verse
        $this->delete("api/friends/{$ana->id}")->assertOK();
        $this->assertSame(0, $this->json($this->get('api/notifications/unread'))['unread']['total']);
        $this->assertSame([], $this->json($this->get('api/notifications'))['notifications']);
    }

    private function addPhoto(int $ownerId): int
    {
        $albumId = (int) model(AlbumModel::class)->insert(['user_id' => $ownerId, 'title' => 'Verano']);

        return (int) model(PhotoModel::class)->insert([
            'album_id' => $albumId,
            'filename' => bin2hex(random_bytes(16)),
            'width'    => 800,
            'height'   => 600,
        ]);
    }
}
