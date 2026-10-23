<?php

namespace Tests\Feature;

use CodeIgniter\HTTP\Exceptions\BadRequestException;
use CodeIgniter\Security\Exceptions\SecurityException;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class AuthApiTest extends ApiTestCase
{
    public function testMeWithoutSessionReturnsNull(): void
    {
        $result = $this->get('api/auth/me');

        $result->assertOK();
        $this->assertNull($this->json($result)['user']);
    }

    public function testLoginWithValidCredentials(): void
    {
        $user = $this->createUser();

        $result = $this->postJson('api/auth/login', ['email' => ' ANA@example.com ', 'password' => self::PASSWORD]);

        $result->assertOK();
        $this->assertSame($user->id, $this->json($result)['user']['id']);
        $this->assertTrue(auth()->loggedIn());
    }

    public function testLoginWithWrongPassword(): void
    {
        $this->createUser();

        $result = $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => 'incorrecta']);

        $result->assertStatus(401);
        $this->assertFalse(auth()->loggedIn());
    }

    public function testLoginValidatesFields(): void
    {
        $result = $this->postJson('api/auth/login', ['email' => 'no-es-un-email']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('email', $this->json($result)['errors']);
        $this->assertArrayHasKey('password', $this->json($result)['errors']);
    }

    public function testLoginIsThrottledPerEmail(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => 'x'])->assertStatus(401);
        }

        $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => 'x'])->assertStatus(429);
    }

    public function testRejectsRequestsWithoutCsrfToken(): void
    {
        $this->createUser();
        $this->sendCsrf = false;

        $this->expectException(SecurityException::class);

        $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => self::PASSWORD]);
    }

    public function testInvalidJsonIsABadRequest(): void
    {
        $this->expectException(BadRequestException::class);

        $this->withBody('{mal')->withHeaders(['Content-Type' => 'application/json'])->post('api/auth/login');
    }

    public function testLogout(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post('api/auth/logout')->assertStatus(204);

        $this->assertFalse(auth()->loggedIn());
    }
}
