<?php

namespace Tests\Feature;

use App\Models\AlbumModel;
use App\Models\FriendshipModel;
use App\Models\PhotoModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class PhotoTagsAndCommentsApiTest extends ApiTestCase
{
    private const AREA = ['x' => 10, 'y' => 20, 'width' => 25, 'height' => 30];

    private User $owner;
    private User $friend;
    private User $stranger;
    private int $photoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = $this->createUser('ana@example.com', 'Ana');
        $this->friend   = $this->createUser('bea@example.com', 'Bea');
        $this->stranger = $this->createUser('carla@example.com', 'Carla');
        model(FriendshipModel::class)->befriend((int) $this->owner->id, (int) $this->friend->id);
        $this->photoId = $this->addPhoto($this->owner);
    }

    public function testEndpointsRequireAuthentication(): void
    {
        $this->get("api/photos/{$this->photoId}")->assertStatus(401);
        $this->postJson("api/photos/{$this->photoId}/comments", ['body' => 'Hola'])->assertStatus(401);
    }

    public function testStrangersCannotSeeOrTouchThePhoto(): void
    {
        $this->actingAs($this->stranger);

        $this->get("api/photos/{$this->photoId}")->assertStatus(404);
        $this->get("api/photos/{$this->photoId}/taggable")->assertStatus(404);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->stranger->id, ...self::AREA])->assertStatus(404);
        $this->postJson("api/photos/{$this->photoId}/comments", ['body' => 'Hola'])->assertStatus(404);
    }

    public function testOwnerTagsAFriendWhoGetsNotified(): void
    {
        $this->actingAs($this->owner);

        $result = $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA]);

        $result->assertStatus(201);
        $tag = $this->json($result)['tags'][0];
        $this->assertSame((int) $this->friend->id, $tag['user']['id']);
        $this->assertEqualsWithDelta(25.0, $tag['width'], 0.001);
        $this->assertTrue($tag['can_remove']);
        $this->seeInDatabase('notifications', ['user_id' => $this->friend->id, 'type' => 'photo_tag', 'photo_id' => $this->photoId]);
    }

    public function testTaggableListsOnlyFriendsWhoCanSeeThePhoto(): void
    {
        // Dani es amigo de Bea pero no de Ana: no puede ver la foto
        $dani = $this->createUser('dani@example.com', 'Dani');
        model(FriendshipModel::class)->befriend((int) $this->friend->id, (int) $dani->id);
        $this->actingAs($this->friend);

        $people = $this->json($this->get("api/photos/{$this->photoId}/taggable"))['people'];
        $this->assertEqualsCanonicalizing([(int) $this->owner->id, (int) $this->friend->id], array_column($people, 'id'));

        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $dani->id, ...self::AREA])->assertStatus(403);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->stranger->id, ...self::AREA])->assertStatus(403);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA])->assertStatus(201);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA])->assertStatus(409);
        $this->dontSeeInDatabase('notifications', ['user_id' => $this->friend->id]);
    }

    public function testTagAreaMustFitInsideThePhoto(): void
    {
        $this->actingAs($this->owner);

        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->owner->id, 'x' => 90, 'y' => 0, 'width' => 20, 'height' => 20])->assertStatus(422);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->owner->id, 'x' => 'a', 'y' => 0, 'width' => 20, 'height' => 20])->assertStatus(422);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->owner->id, 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 20])->assertStatus(422);
    }

    public function testTaggedPersonCanRemoveTheTagButOthersCannot(): void
    {
        $this->actingAs($this->owner);
        $tagId = $this->json($this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA]))['tags'][0]['id'];

        $this->actingAs($this->stranger);
        $this->delete("api/photo-tags/{$tagId}")->assertStatus(404);

        $this->actingAs($this->friend);
        $this->assertTrue($this->json($this->get("api/photos/{$this->photoId}"))['tags'][0]['can_remove']);
        $this->delete("api/photo-tags/{$tagId}")->assertOK();
        $this->dontSeeInDatabase('photo_tags', ['id' => $tagId]);
        $this->dontSeeInDatabase('notifications', ['user_id' => $this->friend->id, 'type' => 'photo_tag']);
    }

    public function testThirdFriendCannotRemoveSomeoneElsesTag(): void
    {
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->owner->id, (int) $eva->id);
        $this->actingAs($this->owner);
        $tagId = $this->json($this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA]))['tags'][0]['id'];

        $this->actingAs($eva);
        $this->assertFalse($this->json($this->get("api/photos/{$this->photoId}"))['tags'][0]['can_remove']);
        $this->delete("api/photo-tags/{$tagId}")->assertStatus(404);
    }

    public function testTagsArePagedInTaggedPhotosAndHiddenFromWhoCannotSeeThem(): void
    {
        $this->actingAs($this->owner);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA])->assertStatus(201);

        $this->actingAs($this->friend);
        $tagged = $this->json($this->get("api/users/{$this->friend->id}/tagged-photos?limit=5"));
        $this->assertSame(1, $tagged['total']);
        $this->assertSame($this->photoId, $tagged['photos'][0]['id']);

        $this->actingAs($this->stranger);
        $this->assertSame(0, $this->json($this->get("api/users/{$this->friend->id}/tagged-photos"))['total']);
        $this->get('api/users/9999/tagged-photos')->assertStatus(404);
    }

    public function testEndingAFriendshipRemovesTagsInEachOthersPhotos(): void
    {
        $this->actingAs($this->owner);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->friend->id, ...self::AREA])->assertStatus(201);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $this->owner->id, ...self::AREA])->assertStatus(201);

        $this->delete("api/friends/{$this->friend->id}")->assertOK();

        $this->dontSeeInDatabase('photo_tags', ['user_id' => $this->friend->id]);
        $this->seeInDatabase('photo_tags', ['user_id' => $this->owner->id]);
    }

    public function testFriendCommentsAndOwnerAndTaggedPeopleAreNotified(): void
    {
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->owner->id, (int) $eva->id);
        $this->actingAs($this->owner);
        $this->postJson("api/photos/{$this->photoId}/tags", ['user_id' => $eva->id, ...self::AREA])->assertStatus(201);

        $this->actingAs($this->friend);
        $result = $this->postJson("api/photos/{$this->photoId}/comments", ['body' => '  ¡Qué foto!  ']);

        $result->assertStatus(201);
        $comment = $this->json($result)['comments'][0];
        $this->assertSame('¡Qué foto!', $comment['body']);
        $this->assertSame((int) $this->friend->id, $comment['author']['id']);
        $this->assertTrue($comment['can_delete']);
        $this->seeInDatabase('notifications', ['user_id' => $this->owner->id, 'type' => 'photo_comment']);
        $this->seeInDatabase('notifications', ['user_id' => $eva->id, 'type' => 'photo_comment']);
        $this->dontSeeInDatabase('notifications', ['user_id' => $this->friend->id, 'type' => 'photo_comment']);
    }

    public function testCommentIsRequiredAndLimited(): void
    {
        $this->actingAs($this->friend);

        $this->postJson("api/photos/{$this->photoId}/comments", ['body' => '   '])->assertStatus(422);
        $this->postJson("api/photos/{$this->photoId}/comments", ['body' => str_repeat('a', 501)])->assertStatus(422);
    }

    public function testCommentsCanBeDeletedByTheirAuthorOrThePhotoOwner(): void
    {
        $eva = $this->createUser('eva@example.com', 'Eva');
        model(FriendshipModel::class)->befriend((int) $this->owner->id, (int) $eva->id);
        $this->actingAs($this->friend);
        $first  = $this->json($this->postJson("api/photos/{$this->photoId}/comments", ['body' => 'Uno']))['comments'][0]['id'];
        $second = $this->json($this->postJson("api/photos/{$this->photoId}/comments", ['body' => 'Dos']))['comments'][1]['id'];

        $this->actingAs($eva);
        $this->delete("api/photo-comments/{$first}")->assertStatus(404);

        $this->actingAs($this->friend);
        $this->delete("api/photo-comments/{$first}")->assertOK();

        $this->actingAs($this->owner);
        $this->assertTrue($this->json($this->get("api/photos/{$this->photoId}"))['comments'][0]['can_delete']);
        $this->delete("api/photo-comments/{$second}")->assertOK();
        $this->dontSeeInDatabase('photo_comments', ['photo_id' => $this->photoId]);
        $this->dontSeeInDatabase('notifications', ['type' => 'photo_comment']);
    }

    /**
     * Foto sin archivo: estos tests no la sirven, solo la etiquetan y comentan.
     */
    private function addPhoto(User $owner): int
    {
        $albumId = (int) model(AlbumModel::class)->insert(['user_id' => $owner->id, 'title' => 'Verano']);

        return (int) model(PhotoModel::class)->insert([
            'album_id' => $albumId,
            'filename' => bin2hex(random_bytes(16)),
            'width'    => 800,
            'height'   => 600,
        ]);
    }
}
