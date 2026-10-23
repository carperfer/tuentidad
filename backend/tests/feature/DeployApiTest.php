<?php

namespace Tests\Feature;

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
