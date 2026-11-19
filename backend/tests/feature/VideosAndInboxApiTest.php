<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\MessageModel;
use App\Models\VideoModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class VideosAndInboxApiTest extends ApiTestCase
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

    public function testReadsYoutubeIdsFromCommonLinks(): void
    {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s',
            'youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://m.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame('dQw4w9WgXcQ', VideoModel::youtubeId($url), $url);
        }
        $this->assertNull(VideoModel::youtubeId('https://vimeo.com/123'));
        $this->assertNull(VideoModel::youtubeId('https://evil.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(VideoModel::youtubeId('https://www.youtube.com/watch?v=corto'));
    }

    public function testFriendsSeeVideosButStrangersDoNot(): void
    {
        $this->actingAs($this->ana);
        $created = $this->postJson('api/videos', ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => '  Echo Beach  ']);
        $created->assertStatus(201);
        $video = $this->json($created)['video'];
        $this->assertSame(['dQw4w9WgXcQ', 'Echo Beach', true], [$video['youtube_id'], $video['title'], $video['is_owner']]);

        $this->actingAs($this->bea);
        $this->assertSame([$video['id']], array_column($this->json($this->get('api/videos'))['videos'], 'id'));
        $mine = $this->json($this->get("api/users/{$this->ana->id}/videos"));
        $this->assertSame(1, $mine['total']);
        $this->assertFalse($mine['videos'][0]['is_owner']);
        $this->delete("api/videos/{$video['id']}")->assertStatus(404);

        $this->actingAs($this->carla);
        $this->get("api/users/{$this->ana->id}/videos")->assertStatus(403);
        $this->assertSame([], $this->json($this->get('api/videos'))['videos']);

        $this->actingAs($this->ana);
        $this->delete("api/videos/{$video['id']}")->assertOK();
        $this->dontSeeInDatabase('videos', ['id' => $video['id']]);
    }

    public function testVideoNeedsAYoutubeLinkAndATitle(): void
    {
        $this->actingAs($this->ana);

        $errors = $this->json($this->postJson('api/videos', ['url' => 'https://vimeo.com/1', 'title' => '']))['errors'];

        $this->assertSame(['url', 'title'], array_keys($errors));
    }

    public function testVideosArePaged(): void
    {
        for ($i = 0; $i < 12; $i++) {
            model(VideoModel::class)->insert(['user_id' => $this->ana->id, 'youtube_id' => 'dQw4w9WgXcQ', 'title' => "Vídeo {$i}"]);
        }
        $this->actingAs($this->ana);

        $first = $this->json($this->get('api/videos'));
        $this->assertCount(10, $first['videos']);
        $this->assertSame(12, $first['total']);
        $this->assertCount(2, $this->json($this->get("api/videos?before={$first['next_before']}"))['videos']);
    }

    public function testInboxListsConversationsWithFriendsByLastMessage(): void
    {
        $dani = $this->createUser('dani@example.com', 'Dani');
        model(FriendshipModel::class)->befriend((int) $this->ana->id, (int) $dani->id);
        $this->actingAs($this->bea);
        $this->postJson("api/chat/{$this->ana->id}/messages", ['body' => 'Hola Ana'])->assertStatus(201);
        $this->actingAs($this->ana);
        $this->postJson("api/chat/{$dani->id}/messages", ['body' => '¿Vienes?'])->assertStatus(201);

        $conversations = $this->json($this->get('api/chat/conversations'))['conversations'];

        $this->assertSame(['Dani', 'Bea'], array_map(static fn (array $item): string => $item['person']['first_name'], $conversations));
        $this->assertSame([0, 1], array_column($conversations, 'unread'));
        $this->assertTrue($conversations[0]['last_message']['mine']);
        $this->assertSame('Hola Ana', $conversations[1]['last_message']['body']);
    }

    public function testConversationHistoryCanBeReadBackwards(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            model(MessageModel::class)->insert(['sender_id' => $this->ana->id, 'recipient_id' => $this->bea->id, 'body' => "Mensaje {$i}"]);
        }
        $this->actingAs($this->ana);

        $latest = $this->json($this->get("api/chat/{$this->bea->id}/messages"))['messages'];
        $this->assertCount(50, $latest);
        $this->assertSame('Mensaje 6', $latest[0]['body']);

        $older = $this->json($this->get("api/chat/{$this->bea->id}/messages?before={$latest[0]['id']}"))['messages'];
        $this->assertSame(['Mensaje 1', 'Mensaje 2', 'Mensaje 3', 'Mensaje 4', 'Mensaje 5'], array_column($older, 'body'));
    }
}
