<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class ChatApiTest extends ApiTestCase
{
    private User $ana;
    private User $bea;
    private User $carla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ana   = $this->createUser('ana@example.com', 'Ana');
        $this->bea   = $this->createUser('bea@example.com', 'Bea');
        $this->carla = $this->createUser('carla@example.com', 'Carla');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $this->bea->id);
    }

    public function testChatRequiresAuthentication(): void
    {
        $this->get('api/chat')->assertStatus(401);
        $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => 'Hola'])->assertStatus(401);
    }

    public function testFriendsExchangeMessagesThatGetMarkedAsRead(): void
    {
        $this->actingAs($this->ana);
        $sent = $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => '  ¿Quedamos?  ']);
        $sent->assertStatus(201);
        $this->assertSame('¿Quedamos?', $this->json($sent)['message']['body']);
        $this->assertTrue($this->json($sent)['message']['mine']);

        $this->actingAs($this->bea);
        $status = $this->json($this->get('api/chat'));
        $this->assertSame(1, $status['unread']);
        $this->assertSame(1, $status['contacts'][0]['unread']);

        $messages = $this->json($this->get("api/chat/{$this->ana->id}/messages"))['messages'];
        $this->assertCount(1, $messages);
        $this->assertFalse($messages[0]['mine']);
        $this->assertSame(0, $this->json($this->get('api/chat'))['unread']);

        $reply = $this->json($this->postJson("api/chat/{$this->ana->id}/messages", ['body' => '¡Vale!']))['message'];

        $this->actingAs($this->ana);
        $newer = $this->json($this->get("api/chat/{$this->bea->id}/messages?after={$messages[0]['id']}"))['messages'];
        $this->assertSame([$reply['id']], array_column($newer, 'id'));
    }

    public function testOnlyFriendsCanChat(): void
    {
        $this->actingAs($this->ana);

        $this->postJson("api/chat/{$this->carla->id}/messages", ['body' => 'Hola'])->assertStatus(403);
        $this->get("api/chat/{$this->carla->id}/messages")->assertStatus(403);
        $this->assertSame([(int) $this->bea->id], array_column($this->json($this->get('api/chat'))['contacts'], 'id'));
    }

    public function testMessageIsRequiredAndLimited(): void
    {
        $this->actingAs($this->ana);

        $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => '   '])->assertStatus(422);
        $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => str_repeat('a', 1001)])->assertStatus(422);
    }

    public function testFriendsWhoPolledRecentlyAreOnline(): void
    {
        $this->actingAs($this->bea);
        $this->get('api/chat')->assertOK();

        $this->actingAs($this->ana);
        $status = $this->json($this->get('api/chat'));
        $this->assertTrue($status['contacts'][0]['online']);
        $this->assertSame(1, $status['online']);

        db_connect()->table('users')->where('id', $this->bea->id)->update(['last_active' => date('Y-m-d H:i:s', time() - 300)]);
        $this->assertFalse($this->json($this->get('api/chat'))['contacts'][0]['online']);
    }

    public function testUnreadFromFormerFriendsIsNotCounted(): void
    {
        $this->actingAs($this->bea);
        $this->postJson("api/chat/{$this->ana->id}/messages", ['body' => 'Hola'])->assertStatus(201);
        $this->delete("api/friends/{$this->ana->id}")->assertOK();

        $this->actingAs($this->ana);
        $this->assertSame(0, $this->json($this->get('api/chat'))['unread']);
    }

    public function testExportIncludesTheConversations(): void
    {
        $this->actingAs($this->ana);
        $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => 'Hola'])->assertStatus(201);
        $this->actingAs($this->bea);
        $this->postJson("api/chat/{$this->ana->id}/messages", ['body' => 'Adiós'])->assertStatus(201);

        $this->actingAs($this->ana);
        $export = json_decode((string) $this->get('api/account/export')->response()->getBody(), true);

        $this->assertSame([
            ['direction' => 'sent', 'person' => 'Bea Pruebas', 'body' => 'Hola'],
            ['direction' => 'received', 'person' => 'Bea Pruebas', 'body' => 'Adiós'],
        ], array_map(static fn (array $message): array => array_diff_key($message, ['created_at' => true]), $export['messages']));
    }

    public function testDeletingTheAccountRemovesItsMessages(): void
    {
        $this->actingAs($this->ana);
        $this->postJson("api/chat/{$this->bea->id}/messages", ['body' => 'Hola'])->assertStatus(201);

        $this->withBodyFormat('json')->delete('api/account', ['password' => self::PASSWORD])->assertOK();

        $this->dontSeeInDatabase('messages', ['recipient_id' => $this->bea->id]);
    }
}
