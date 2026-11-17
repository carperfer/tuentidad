<?php

namespace Tests\Feature;

use App\Libraries\PhotoProcessor;
use App\Models\AlbumModel;
use App\Models\FriendshipModel;
use App\Models\PhotoModel;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;
use Tests\Support\TestImages;

/**
 * @internal
 */
final class AccountApiTest extends ApiTestCase
{
    public function testAccountEndpointsRequireAuthentication(): void
    {
        $this->get('api/account/export')->assertStatus(401);
        $this->withBodyFormat('json')->delete('api/account', ['password' => self::PASSWORD])->assertStatus(401);
    }

    public function testExportIncludesTheUsersDataAsADownload(): void
    {
        $ana = $this->createUser('ana@example.com', 'Ana');
        $bea = $this->createUser('bea@example.com', 'Bea');
        model(FriendshipModel::class)->befriend((int) $ana->id, (int) $bea->id);
        $albumId = (int) model(AlbumModel::class)->insert(['user_id' => $ana->id, 'title' => 'Verano']);
        $photoId = (int) model(PhotoModel::class)->insert(['album_id' => $albumId, 'filename' => bin2hex(random_bytes(16)), 'width' => 800, 'height' => 600]);
        $this->actingAs($ana);
        $this->postJson("api/photos/{$photoId}/comments", ['body' => 'Mi comentario'])->assertStatus(201);
        $this->postJson('api/invitations', ['email' => 'carla@example.com'])->assertStatus(201);

        $result = $this->get('api/account/export');

        $result->assertOK();
        $result->assertHeader('Content-Disposition', 'attachment; filename="tuentidad-mis-datos-' . date('Y-m-d') . '.json"');
        $data = json_decode((string) $result->response()->getBody(), true);
        $this->assertSame('ana@example.com', $data['account']['email']);
        $this->assertSame('Bea Pruebas', $data['friends'][0]['name']);
        $this->assertSame('Verano', $data['albums'][0]['title']);
        $this->assertSame($photoId, $data['albums'][0]['photos'][0]['id']);
        $this->assertSame('Mi comentario', $data['photo_comments'][0]['body']);
        $this->assertSame('carla@example.com', $data['invitations_sent'][0]['email']);
    }

    public function testDeletingRequiresTheCurrentPassword(): void
    {
        $ana = $this->createUser();
        $this->actingAs($ana);

        $result = $this->withBodyFormat('json')->delete('api/account', ['password' => 'incorrecta']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('password', $this->json($result)['errors']);
        $this->assertNotNull(model(UserModel::class)->find($ana->id));
    }

    public function testDeletingRemovesTheAccountItsDataAndItsFiles(): void
    {
        $ana       = $this->createUser('ana@example.com', 'Ana');
        $bea       = $this->createUser('bea@example.com', 'Bea');
        $processor = new PhotoProcessor(WRITEPATH . 'uploads/photos');
        $source    = TestImages::jpeg(tempnam(sys_get_temp_dir(), 'photo'), 400, 300);
        $stored    = $processor->process($source);
        unlink($source);
        model(FriendshipModel::class)->befriend((int) $ana->id, (int) $bea->id);
        $albumId = (int) model(AlbumModel::class)->insert(['user_id' => $ana->id, 'title' => 'Verano']);
        $photoId = (int) model(PhotoModel::class)->insert(['album_id' => $albumId, ...$stored]);

        // Ana invita a alguien y comenta y etiqueta en una foto de Bea
        $beaAlbum = (int) model(AlbumModel::class)->insert(['user_id' => $bea->id, 'title' => 'Fiesta']);
        $beaPhoto = (int) model(PhotoModel::class)->insert(['album_id' => $beaAlbum, 'filename' => bin2hex(random_bytes(16)), 'width' => 800, 'height' => 600]);
        $this->actingAs($ana);
        $this->postJson('api/invitations', ['email' => 'carla@example.com'])->assertStatus(201);
        $this->postJson("api/photos/{$beaPhoto}/comments", ['body' => 'Hola'])->assertStatus(201);
        $this->postJson("api/photos/{$beaPhoto}/tags", ['user_id' => $ana->id, 'x' => 0, 'y' => 0, 'width' => 50, 'height' => 50])->assertStatus(201);

        $this->withBodyFormat('json')->delete('api/account', ['password' => self::PASSWORD])->assertOK();

        $this->dontSeeInDatabase('users', ['id' => $ana->id]);
        $this->dontSeeInDatabase('auth_identities', ['secret' => 'ana@example.com']);
        $this->dontSeeInDatabase('albums', ['id' => $albumId]);
        $this->dontSeeInDatabase('photos', ['id' => $photoId]);
        $this->dontSeeInDatabase('friendships', ['friend_id' => $bea->id]);
        $this->dontSeeInDatabase('invitations', ['email' => 'carla@example.com']);
        $this->dontSeeInDatabase('photo_comments', ['photo_id' => $beaPhoto]);
        $this->dontSeeInDatabase('photo_tags', ['photo_id' => $beaPhoto]);
        $this->dontSeeInDatabase('notifications', ['actor_id' => $ana->id]);
        $this->assertFileDoesNotExist($processor->largePath($stored['filename']));
        $this->assertFileDoesNotExist($processor->thumbPath($stored['filename']));
        $this->get('api/auth/me')->assertJSONFragment(['user' => null]);
    }
}
