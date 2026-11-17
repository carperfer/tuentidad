<?php

namespace Tests\Feature;

use App\Libraries\PhotoProcessor;
use App\Models\AlbumModel;
use App\Models\PhotoModel;
use Tests\Support\ApiTestCase;
use Tests\Support\TestImages;

/**
 * Prueba de integración del recorrido principal de tuentidad, solo con la API:
 * invitación, registro, amistad, fotos, etiquetas, comentarios, avisos y baja.
 *
 * @internal
 */
final class MainFlowTest extends ApiTestCase
{
    private const BEA_PASSWORD = 'Montaña-Roja-42';

    private ?string $storedFile = null;

    protected function tearDown(): void
    {
        if ($this->storedFile !== null) {
            (new PhotoProcessor(WRITEPATH . 'uploads/photos'))->delete($this->storedFile);
        }

        parent::tearDown();
    }

    public function testFromInvitationToDeletingTheAccount(): void
    {
        $this->createUser('ana@example.com', 'Ana');

        // Ana entra e invita a Bea por email
        $this->login('ana@example.com', self::PASSWORD);
        $this->postJson('api/invitations', ['email' => 'bea@example.com'])->assertStatus(201);
        $token = $this->tokenFromEmail('registro');
        $this->post('api/auth/logout')->assertStatus(204);

        // Bea se registra con la invitación y pide amistad a Ana
        $this->get("api/invitations/{$token}")->assertOK();
        $registered = $this->postJson('api/auth/register', [
            'token'            => $token,
            'first_name'       => 'Bea',
            'last_name'        => 'Ruiz',
            'birthdate'        => '1999-04-01',
            'password'         => self::BEA_PASSWORD,
            'password_confirm' => self::BEA_PASSWORD,
            'accept_terms'     => true,
        ]);
        $registered->assertStatus(201);
        $beaId = $this->json($registered)['user']['id'];
        $anaId = $this->json($this->get('api/friends/search?q=Ana'))['users'][0]['id'];
        $this->postJson('api/friend-requests', ['user_id' => $anaId])->assertStatus(201);
        $this->post('api/auth/logout')->assertStatus(204);

        // Ana ve el aviso, acepta y sube una foto en la que etiqueta a Bea
        $this->login('ana@example.com', self::PASSWORD);
        $this->assertSame(1, $this->json($this->get('api/notifications/unread'))['unread']['friend_request']);
        $requestId = $this->json($this->get('api/friend-requests'))['incoming'][0]['id'];
        $this->post("api/friend-requests/{$requestId}/accept")->assertOK();
        $albumId = $this->json($this->postJson('api/albums', ['title' => 'Verano']))['album']['id'];
        $photoId = $this->storePhoto($albumId);
        $this->postJson("api/photos/{$photoId}/tags", ['user_id' => $beaId, 'x' => 30, 'y' => 20, 'width' => 25, 'height' => 40])->assertStatus(201);
        $this->post('api/auth/logout')->assertStatus(204);

        // Bea recibe el aviso de la etiqueta, ve la foto, comenta y se exporta los datos
        $this->login('bea@example.com', self::BEA_PASSWORD);
        $this->assertSame(1, $this->json($this->get('api/notifications/unread'))['unread']['photo_tag']);
        $this->assertSame($photoId, $this->json($this->get("api/users/{$beaId}/tagged-photos"))['photos'][0]['id']);
        $this->get("api/photos/{$photoId}")->assertOK();
        $this->get("api/photos/{$photoId}/image")->assertOK();
        $this->postJson("api/photos/{$photoId}/comments", ['body' => '¡Qué día!'])->assertStatus(201);
        $this->assertSame(0, $this->json($this->get('api/notifications/unread'))['unread']['total']);
        $export = json_decode((string) $this->get('api/account/export')->response()->getBody(), true);
        $this->assertSame('Ana Pruebas', $export['friends'][0]['name']);
        $this->assertSame($photoId, $export['photos_tagged_in'][0]['photo_id']);

        // Bea se da de baja: desaparecen su etiqueta y su comentario de la foto de Ana
        $this->withBodyFormat('json')->delete('api/account', ['password' => self::BEA_PASSWORD])->assertOK();
        $this->postJson('api/auth/login', ['email' => 'bea@example.com', 'password' => self::BEA_PASSWORD])->assertStatus(401);

        $this->login('ana@example.com', self::PASSWORD);
        $this->assertSame(0, $this->json($this->get('api/notifications/unread'))['unread']['total']);
        $photo = $this->json($this->get("api/photos/{$photoId}"));
        $this->assertSame([], $photo['tags']);
        $this->assertSame([], $photo['comments']);
        $this->assertSame([], $this->json($this->get('api/friends'))['friends']);
    }

    private function login(string $email, string $password): void
    {
        $this->postJson('api/auth/login', ['email' => $email, 'password' => $password])->assertOK();
    }

    /**
     * Sube la foto como lo haría el endpoint de subida: procesada con GD.
     */
    private function storePhoto(int $albumId): int
    {
        $this->assertNotNull(model(AlbumModel::class)->find($albumId));
        $processor = new PhotoProcessor(WRITEPATH . 'uploads/photos');
        $source    = TestImages::jpeg(tempnam(sys_get_temp_dir(), 'photo'), 400, 300);
        $stored    = $processor->process($source);
        unlink($source);
        $this->storedFile = $stored['filename'];

        return (int) model(PhotoModel::class)->insert(['album_id' => $albumId, ...$stored]);
    }
}
