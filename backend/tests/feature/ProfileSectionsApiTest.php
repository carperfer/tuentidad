<?php

namespace Tests\Feature;

use App\Models\FriendshipModel;
use App\Models\ProfileLinkModel;
use CodeIgniter\Shield\Entities\User;
use Tests\Support\ApiTestCase;

/**
 * «Redes», enlaces a redes sociales y tablón del perfil.
 *
 * @internal
 */
final class ProfileSectionsApiTest extends ApiTestCase
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

    public function testNetworksAreSavedAndShownToFriendsByDefault(): void
    {
        $this->actingAs($this->ana);
        $profile = $this->json($this->withBodyFormat('json')->put('api/profile/networks', [
            'visibility' => 'friends',
            'items'      => [
                ['kind' => 'university', 'name' => 'UNED', 'detail' => 'Antropología', 'start_year' => 2006, 'end_year' => null],
                ['kind' => 'work', 'name' => 'Abogados Sauceda', 'detail' => '', 'start_year' => 2009, 'end_year' => ''],
                ['kind' => 'school', 'name' => 'San José de Calasanz', 'start_year' => 1990, 'end_year' => 1992],
            ],
        ]))['profile'];

        $this->assertSame('UNED', $profile['networks']['university'][0]['name']);
        $this->assertNull($profile['networks']['work'][0]['end_year']);
        $this->assertSame('friends', $profile['visibility']['networks']);

        $this->actingAs($this->bea);
        $this->assertSame(1992, $this->json($this->get("api/profiles/{$this->ana->id}"))['profile']['networks']['school'][0]['end_year']);

        $this->actingAs($this->carla);
        $this->assertNull($this->json($this->get("api/profiles/{$this->ana->id}"))['profile']['networks']);
    }

    public function testNetworksAreValidated(): void
    {
        $this->actingAs($this->ana);

        $errors = $this->json($this->withBodyFormat('json')->put('api/profile/networks', [
            'visibility' => 'friends',
            'items'      => [
                ['kind' => 'gym', 'name' => 'X'],
                ['kind' => 'work', 'name' => ''],
                ['kind' => 'work', 'name' => 'Bar', 'start_year' => 2010, 'end_year' => 2005],
            ],
        ]))['errors'];

        $this->assertSame(['items.0', 'items.1', 'items.2'], array_keys($errors));
        $this->withBodyFormat('json')->put('api/profile/networks', ['visibility' => 'todos', 'items' => []])->assertStatus(422);
    }

    public function testSocialLinksOnlyFromKnownNetworks(): void
    {
        $this->assertSame(['url' => 'https://instagram.com/ana', 'network' => 'Instagram'], ProfileLinkModel::normalize('www.instagram.com/ana/'));
        $this->assertNull(ProfileLinkModel::normalize('https://example.com/ana'));
        $this->assertNull(ProfileLinkModel::normalize('https://x.com/'));

        $this->actingAs($this->ana);
        $this->withBodyFormat('json')->put('api/profile/links', ['visibility' => 'public', 'links' => ['https://evil.com/x']])->assertStatus(422);
        $profile = $this->json($this->withBodyFormat('json')->put('api/profile/links', [
            'visibility' => 'public',
            'links'      => ['https://twitter.com/ana', '', 'tiktok.com/@ana'],
        ]))['profile'];
        $this->assertSame(['X', 'TikTok'], array_column($profile['links'], 'network'));

        // Públicos: los ve también quien no es amigo
        $this->actingAs($this->carla);
        $this->assertCount(2, $this->json($this->get("api/profiles/{$this->ana->id}"))['profile']['links']);
    }

    public function testFriendsWriteOnTheWallAndTheOwnerIsNotified(): void
    {
        $this->actingAs($this->bea);
        $created = $this->postJson("api/users/{$this->ana->id}/wall", ['body' => '  ¡Felicidades!  ']);
        $created->assertStatus(201);
        $post = $this->json($created)['post'];
        $this->assertSame(['¡Felicidades!', true], [$post['body'], $post['can_delete']]);
        $this->seeInDatabase('notifications', ['user_id' => $this->ana->id, 'type' => 'wall_post', 'wall_post_id' => $post['id']]);

        $this->actingAs($this->carla);
        $this->get("api/users/{$this->ana->id}/wall")->assertStatus(403);
        $this->postJson("api/users/{$this->ana->id}/wall", ['body' => 'Hola'])->assertStatus(403);
        $this->delete("api/wall-posts/{$post['id']}")->assertStatus(404);

        $this->actingAs($this->ana);
        $posts = $this->json($this->get("api/users/{$this->ana->id}/wall"))['posts'];
        $this->assertSame('Bea', $posts[0]['author']['first_name']);
        $this->assertTrue($posts[0]['can_delete']);
        $this->delete("api/wall-posts/{$post['id']}")->assertOK();
        $this->dontSeeInDatabase('notifications', ['type' => 'wall_post']);
    }

    public function testWallIsPagedAndValidated(): void
    {
        $this->actingAs($this->ana);
        $this->postJson("api/users/{$this->ana->id}/wall", ['body' => '   '])->assertStatus(422);
        for ($i = 1; $i <= 12; $i++) {
            $this->postJson("api/users/{$this->ana->id}/wall", ['body' => "Nota {$i}"])->assertStatus(201);
        }

        $first = $this->json($this->get("api/users/{$this->ana->id}/wall"));
        $this->assertCount(10, $first['posts']);
        $this->assertSame('Nota 12', $first['posts'][0]['body']);
        $this->assertSame(['Nota 2', 'Nota 1'], array_column($this->json($this->get("api/users/{$this->ana->id}/wall?before={$first['next_before']}"))['posts'], 'body'));
        // En tu propio tablón no te avisa
        $this->dontSeeInDatabase('notifications', ['type' => 'wall_post']);
    }

    public function testExportAndDeletionIncludeTheNewSections(): void
    {
        $this->actingAs($this->ana);
        $this->withBodyFormat('json')->put('api/profile/links', ['visibility' => 'friends', 'links' => ['https://github.com/ana']])->assertOK();
        $this->postJson("api/users/{$this->bea->id}/wall", ['body' => 'Para Bea'])->assertStatus(201);

        $export = json_decode((string) $this->get('api/account/export')->response()->getBody(), true);
        $this->assertSame(['https://github.com/ana'], $export['social_links']);
        $this->assertSame('Para Bea', $export['wall_posts_written'][0]['body']);

        $this->withBodyFormat('json')->delete('api/account', ['password' => self::PASSWORD])->assertOK();
        $this->dontSeeInDatabase('wall_posts', ['profile_id' => $this->bea->id]);
        $this->dontSeeInDatabase('profile_links', ['user_id' => $this->ana->id]);
    }
}
