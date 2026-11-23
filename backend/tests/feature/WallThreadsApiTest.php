<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;
use Tests\Support\FakeLinkPreviewer;
use Tests\Support\TestImages;

/**
 * Hilos de respuestas y tarjetas de enlaces en el tablón.
 *
 * @internal
 */
final class WallThreadsApiTest extends ApiTestCase
{
    private User $ana;
    private User $bea;
    private User $carla;
    private FakeLinkPreviewer $previewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana   = $this->createUser('ana@example.com', 'Ana');
        $this->bea   = $this->createUser('bea@example.com', 'Bea');
        $this->carla = $this->createUser('carla@example.com', 'Carla');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $this->bea->id);
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $this->carla->id);

        $jpeg            = (string) file_get_contents(TestImages::jpeg(tempnam(sys_get_temp_dir(), 'img'), 800, 600));
        $this->previewer = new FakeLinkPreviewer([
            'https://example.com/receta' => ['type' => 'text/html', 'body' => '<meta property="og:title" content="Tortilla de patatas"><meta property="og:description" content="Con cebolla"><meta property="og:site_name" content="Cocina"><meta property="og:image" content="https://example.com/t.jpg">'],
            'https://example.com/t.jpg'  => ['type' => 'image/jpeg', 'body' => $jpeg],
        ]);
        Services::injectMock('linkPreviewer', $this->previewer);
    }

    public function testRepliesFormAThreadAndNotifyTheAuthor(): void
    {
        $this->actingAs($this->bea);
        $post = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => '¿Vienes el sábado? 🎉']))['post'];
        $this->assertSame([], $post['replies']);

        $this->actingAs($this->ana);
        $reply = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => '¡Claro! 😄', 'parent_id' => $post['id']]))['post'];
        $this->assertSame($post['id'], $reply['parent_id']);
        $this->seeInDatabase('notifications', ['user_id' => $this->bea->id, 'type' => 'wall_reply', 'wall_post_id' => $reply['id']]);

        // Responder a una respuesta sigue en el mismo hilo; avisa a quien empezó el hilo y al dueño
        $this->actingAs($this->carla);
        $second = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Y yo', 'parent_id' => $reply['id']]))['post'];
        $this->assertSame($post['id'], $second['parent_id']);
        $this->seeInDatabase('notifications', ['user_id' => $this->bea->id, 'type' => 'wall_reply', 'wall_post_id' => $second['id']]);
        $this->seeInDatabase('notifications', ['user_id' => $this->ana->id, 'type' => 'wall_post', 'wall_post_id' => $second['id']]);

        $posts = $this->json($this->get("api/users/{$this->ana->id}/wall"))['posts'];
        $this->assertCount(1, $posts);
        $this->assertSame('¿Vienes el sábado? 🎉', $posts[0]['body']);
        $this->assertSame(['¡Claro! 😄', 'Y yo'], array_column($posts[0]['replies'], 'body'));
        // Carla borra su respuesta, pero no la de Ana
        $this->assertSame([false, true], array_column($posts[0]['replies'], 'can_delete'));

        $this->actingAs($this->bea);
        $notifications = $this->json($this->get('api/notifications'))['notifications'];
        $this->assertSame(['type' => 'wall_reply', 'wall' => ['profile_id' => (int) $this->ana->id]], ['type' => $notifications[0]['type'], 'wall' => $notifications[0]['wall']]);

        // Al borrar el mensaje se borra el hilo
        $this->delete("api/wall-posts/{$post['id']}")->assertOK();
        $this->dontSeeInDatabase('wall_posts', ['profile_id' => $this->ana->id]);
    }

    public function testRepliesMustBelongToTheSameWall(): void
    {
        $this->actingAs($this->ana);
        $onBea = $this->json($this->postJson("api/users/{$this->bea->id}/wall", ['body' => 'Hola Bea']))['post'];

        $this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Hola', 'parent_id' => $onBea['id']])->assertStatus(404);
        $this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Hola', 'parent_id' => 999999])->assertStatus(404);
        $this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Hola', 'parent_id' => 'x'])->assertStatus(404);
    }

    public function testLinksGetACardReadFromThePage(): void
    {
        $this->actingAs($this->bea);
        $preview = $this->json($this->postJson('api/link-previews', ['url' => 'Mira https://example.com/receta']))['preview'];
        $this->assertSame(['Tortilla de patatas', 'Con cebolla', 'Cocina'], [$preview['title'], $preview['description'], $preview['site_name']]);
        $this->assertSame("/api/link-previews/{$preview['id']}/image", $preview['image_url']);

        $image = $this->get($preview['image_url']);
        $image->assertOK();
        $this->assertStringStartsWith('image/jpeg', $image->response()->getHeaderLine('Content-Type'));

        // Al publicar se usa la tarjeta guardada: la página no se vuelve a leer
        $post = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Mira https://example.com/receta']))['post'];
        $this->assertSame($preview, $post['link_preview']);
        $this->assertSame(['https://example.com/receta', 'https://example.com/t.jpg'], $this->previewer->requested);

        $without = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Otra vez https://example.com/receta', 'link_preview' => false]))['post'];
        $this->assertNull($without['link_preview']);

        $this->assertSame($preview['id'], $this->json($this->get("api/users/{$this->ana->id}/wall"))['posts'][1]['link_preview']['id']);
    }

    public function testUnreadablePagesHaveNoCardAndAreNotRetried(): void
    {
        $this->actingAs($this->bea);
        $this->assertNull($this->json($this->postJson('api/link-previews', ['url' => 'https://example.com/caida']))['preview']);
        $this->assertNull($this->json($this->postJson('api/link-previews', ['url' => 'sin enlace']))['preview']);
        $post = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'https://example.com/caida']))['post'];
        $this->assertNull($post['link_preview']);
        $this->assertSame(['https://example.com/caida'], $this->previewer->requested);
        $this->get('api/link-previews/999/image')->assertStatus(404);
    }

    public function testUnusedCardsAreDeletedWithTheirPosts(): void
    {
        $this->actingAs($this->bea);
        $post = $this->json($this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'https://example.com/receta']))['post'];
        $file = WRITEPATH . 'uploads/link-previews/' . db_connect()->table('link_previews')->get()->getRowArray()['image'];
        $this->assertFileExists($file);

        // Las recién leídas se conservan una hora por si alguien las está usando
        db_connect()->table('link_previews')->update(['created_at' => date('Y-m-d H:i:s', time() - 2 * HOUR)]);
        $this->delete("api/wall-posts/{$post['id']}")->assertOK();

        $this->dontSeeInDatabase('link_previews', ['url' => 'https://example.com/receta']);
        $this->assertFileDoesNotExist($file);
    }
}
