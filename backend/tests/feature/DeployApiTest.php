<?php

namespace Tests\Feature;

use App\Libraries\DatabaseBackup;
use App\Models\UserModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class DeployApiTest extends ApiTestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config('Tuentidad')->deployToken = self::TOKEN;
        // Se autentican con token: no necesitan CSRF
        $this->sendCsrf = false;
    }

    public function testDisabledWithoutConfiguredToken(): void
    {
        config('Tuentidad')->deployToken = '';

        $this->withToken(self::TOKEN)->post('api/deploy/migrate')->assertStatus(404);
        $this->withToken('')->post('api/deploy/migrate')->assertStatus(404);
    }

    public function testRejectsWrongToken(): void
    {
        $this->withToken(str_repeat('f', 64))->post('api/deploy/migrate')->assertStatus(401);
        $this->post('api/deploy/migrate')->assertStatus(401);
    }

    public function testMigrate(): void
    {
        $result = $this->withToken(self::TOKEN)->post('api/deploy/migrate');

        $result->assertOK();
        $this->assertGreaterThan(0, $this->json($result)['migrations']);
    }

    public function testBackupCanBeRestored(): void
    {
        $user = $this->createUser('ana@example.com', 'Ana');
        model(UserModel::class)->update((int) $user->id, ['bio' => "Línea con 'comillas'; y\npunto y coma"]);

        $result = $this->withToken(self::TOKEN)->post('api/deploy/backup');

        $result->assertStatus(201);
        $filename = $this->json($result)['filename'];
        $path     = WRITEPATH . 'backups/' . $filename;
        $this->assertFileExists($path);

        try {
            // Se pierden datos y se restaura la copia como lo haría phpMyAdmin
            db_connect()->table('users')->where('id', $user->id)->update(['bio' => 'Cambiada']);
            $mysqli = db_connect()->connID;
            $this->assertTrue($mysqli->multi_query((string) gzdecode((string) file_get_contents($path))), $mysqli->error);
            do {
                $mysqli->store_result();
            } while ($mysqli->more_results() && $mysqli->next_result());
            $this->assertSame('', $mysqli->error);

            $this->seeInDatabase('users', ['id' => $user->id, 'bio' => "Línea con 'comillas'; y\npunto y coma"]);
            $this->seeInDatabase('auth_identities', ['secret' => 'ana@example.com']);
        } finally {
            unlink($path);
        }
    }

    public function testBackupRequiresTheToken(): void
    {
        $this->post('api/deploy/backup')->assertStatus(401);
    }

    public function testOldBackupsArePruned(): void
    {
        $directory = sys_get_temp_dir() . '/tuentidad-backups-' . bin2hex(random_bytes(4));
        mkdir($directory);
        for ($day = 1; $day <= 3; $day++) {
            touch("{$directory}/tuentidad-2026010{$day}-030000.sql.gz");
        }

        $backup = new DatabaseBackup(db_connect(), $directory, 2);
        $result = $backup->create();

        $this->assertSame([$result['filename'], 'tuentidad-20260103-030000.sql.gz'], $backup->list());
        array_map('unlink', glob("{$directory}/*") ?: []);
        rmdir($directory);
    }

    public function testCreatesFirstUserOnlyOnce(): void
    {
        $data = [
            'email'      => 'Admin@Example.com',
            'first_name' => 'Carlos',
            'last_name'  => 'Pérez',
            'birthdate'  => '1985-03-10',
            'password'   => 'Faro.Viejo.Sur.31',
        ];

        $result = $this->withToken(self::TOKEN)->withBodyFormat('json')->post('api/deploy/first-user', $data);

        $result->assertStatus(201);
        $this->assertSame('admin@example.com', $this->json($result)['user']['email']);
        $this->seeInDatabase('albums', ['user_id' => $this->json($result)['user']['id'], 'title' => 'Mis fotos']);
        $this->sendCsrf = true;
        $this->postJson('api/auth/login', ['email' => 'admin@example.com', 'password' => 'Faro.Viejo.Sur.31'])->assertOK();

        $this->sendCsrf = false;
        $this->withToken(self::TOKEN)->withBodyFormat('json')->post('api/deploy/first-user', ['email' => 'otro@example.com'] + $data)
            ->assertStatus(409);
    }

    public function testFirstUserIsValidated(): void
    {
        $result = $this->withToken(self::TOKEN)->withBodyFormat('json')->post('api/deploy/first-user', ['email' => 'x']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('password', $this->json($result)['errors']);
    }

    private function withToken(string $token): self
    {
        return $this->withHeaders($token === '' ? [] : ['Authorization' => "Bearer {$token}"]);
    }
}
