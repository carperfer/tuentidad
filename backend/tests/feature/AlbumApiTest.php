<?php

namespace Tests\Feature;

use App\Libraries\PhotoProcessor;
use App\Models\AlbumModel;
use App\Models\FriendshipModel;
use App\Models\PhotoModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;
use Tests\Support\TestImages;

/**
 * @internal
 */
final class AlbumApiTest extends ApiTestCase
{
    private PhotoProcessor $processor;

    /**
     * @var list<string>
     */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new PhotoProcessor(WRITEPATH . 'uploads/photos');
    }

    protected function tearDown(): void
    {
        foreach ($this->storedFiles as $filename) {
            $this->processor->delete($filename);
        }

        parent::tearDown();
    }

    public function testAlbumEndpointsRequireAuthentication(): void
    {
        $this->get('api/users/1/albums')->assertStatus(401);
        $this->postJson('api/albums', ['title' => 'Verano'])->assertStatus(401);
    }

    public function testOwnerCreatesEditsAndListsAlbums(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $created = $this->postJson('api/albums', ['title' => 'Verano', 'description' => 'En la playa']);
        $created->assertStatus(201);
        $albumId = $this->json($created)['album']['id'];

        $this->withBodyFormat('json')->put("api/albums/{$albumId}", ['title' => 'Verano 2026', 'description' => ''])->assertOK();
        $this->addPhoto($albumId);

        $albums = $this->json($this->get("api/users/{$user->id}/albums"))['albums'];
        $this->assertCount(1, $albums);
        $this->assertSame('Verano 2026', $albums[0]['title']);
        $this->assertSame(1, $albums[0]['photo_count']);
        $this->assertIsInt($albums[0]['cover_photo_id']);
    }

    public function testAlbumTitleIsRequired(): void
    {
        $this->actingAs($this->createUser());

        $result = $this->postJson('api/albums', ['title' => '  ']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('title', $this->json($result)['errors']);
    }

    public function testFriendsCanSeeAlbumsAndPhotosButOthersCannot(): void
    {
        [$owner, $albumId, $photoId] = $this->albumWithPhoto();
        $friend                      = $this->createUser('bea@example.com', 'Bea');
        $stranger                    = $this->createUser('carla@example.com', 'Carla');
        model(FriendshipModel::class)->befriend((int) $owner->id, (int) $friend->id);

        $this->actingAs($friend);
        $this->get("api/users/{$owner->id}/albums")->assertOK();
        $album = $this->json($this->get("api/albums/{$albumId}"))['album'];
        $this->assertFalse($album['is_owner']);
        $this->assertSame("/api/photos/{$photoId}/image", $album['photos'][0]['url']);
        $image = $this->get("api/photos/{$photoId}/image");
        $image->assertOK();
        $image->assertHeader('Content-Type', 'image/jpeg');
        $this->get("api/photos/{$photoId}/thumb")->assertOK();

        $this->actingAs($stranger);
        $this->get("api/users/{$owner->id}/albums")->assertStatus(403);
        $this->get("api/albums/{$albumId}")->assertStatus(404);
        $this->get("api/photos/{$photoId}/image")->assertStatus(404);
        $this->get("api/photos/{$photoId}/thumb")->assertStatus(404);
    }

    public function testOnlyTheOwnerCanChangeAlbumsAndPhotos(): void
    {
        [$owner, $albumId, $photoId] = $this->albumWithPhoto();
        $friend                      = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $owner->id, (int) $friend->id);
        $this->actingAs($friend);

        $this->withBodyFormat('json')->put("api/albums/{$albumId}", ['title' => 'Mío'])->assertStatus(404);
        $this->post("api/albums/{$albumId}/photos")->assertStatus(404);
        $this->delete("api/photos/{$photoId}")->assertStatus(404);
        $this->delete("api/albums/{$albumId}")->assertStatus(404);
        $this->assertNotNull(model(AlbumModel::class)->find($albumId));
    }

    public function testUploadWithoutAFileIsRejected(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);
        $albumId = model(AlbumModel::class)->insert(['user_id' => $user->id, 'title' => 'Verano']);

        $result = $this->post("api/albums/{$albumId}/photos");

        $result->assertStatus(422);
        $this->assertArrayHasKey('photo', $this->json($result)['errors']);
    }

    public function testDeletingPhotosAndAlbumsRemovesTheirFiles(): void
    {
        [$owner, $albumId, $photoId] = $this->albumWithPhoto();
        $secondId                    = $this->addPhoto($albumId);
        $this->actingAs($owner);
        $first  = model(PhotoModel::class)->find($photoId)['filename'];
        $second = model(PhotoModel::class)->find($secondId)['filename'];

        $this->delete("api/photos/{$photoId}")->assertOK();
        $this->assertFileDoesNotExist($this->processor->largePath($first));
        $this->assertFileExists($this->processor->largePath($second));

        $this->delete("api/albums/{$albumId}")->assertOK();
        $this->assertNull(model(AlbumModel::class)->find($albumId));
        $this->assertFileDoesNotExist($this->processor->largePath($second));
        $this->assertFileDoesNotExist($this->processor->thumbPath($second));
    }

    /**
     * @return array{User, int, int}
     */
    private function albumWithPhoto(): array
    {
        $owner   = $this->createUser();
        $albumId = (int) model(AlbumModel::class)->insert(['user_id' => $owner->id, 'title' => 'Verano']);

        return [$owner, $albumId, $this->addPhoto($albumId)];
    }

    private function addPhoto(int $albumId): int
    {
        $source = TestImages::jpeg(tempnam(sys_get_temp_dir(), 'photo'), 400, 300);
        $stored = $this->processor->process($source);
        unlink($source);
        $this->storedFiles[] = $stored['filename'];

        return (int) model(PhotoModel::class)->insert(['album_id' => $albumId, ...$stored]);
    }
}
