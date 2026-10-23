<?php

namespace Tests\Feature;

use App\Models\PasswordResetModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class PasswordApiTest extends ApiTestCase
{
    private const NEW_PASSWORD = 'Nube.Verde.Lejos.7';

    public function testForgotSendsResetLinkToExistingUser(): void
    {
        $this->createUser();

        $result = $this->postJson('api/auth/forgot-password', ['email' => 'ANA@example.com']);

        $result->assertOK();
        $this->assertCount(1, $this->email->sent);
        $this->assertSame(['ana@example.com'], $this->email->sent[0]['to']);
        $this->tokenFromEmail('restablecer-contrasena');
    }

    public function testForgotGivesSameAnswerForUnknownEmail(): void
    {
        $this->createUser();
        $known = $this->json($this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']));

        $result = $this->postJson('api/auth/forgot-password', ['email' => 'nadie@example.com']);

        $result->assertOK();
        $this->assertSame($known, $this->json($result));
        $this->assertCount(1, $this->email->sent);
    }

    public function testResetChangesPasswordOnce(): void
    {
        $this->createUser();
        $this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']);
        $token = $this->tokenFromEmail('restablecer-contrasena');

        $this->postJson('api/auth/reset-password', $this->resetData($token))->assertOK();
        $this->postJson('api/auth/reset-password', $this->resetData($token))->assertStatus(404);

        $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => self::PASSWORD])->assertStatus(401);
        $this->postJson('api/auth/login', ['email' => 'ana@example.com', 'password' => self::NEW_PASSWORD])->assertOK();
    }

    public function testNewRequestInvalidatesPreviousLink(): void
    {
        $this->createUser();
        $this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']);
        $first = $this->tokenFromEmail('restablecer-contrasena');
        $this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']);

        $this->postJson('api/auth/reset-password', $this->resetData($first))->assertStatus(404);
        $this->postJson('api/auth/reset-password', $this->resetData($this->tokenFromEmail('restablecer-contrasena')))->assertOK();
    }

    public function testExpiredLinkIsRejected(): void
    {
        $this->createUser();
        $this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']);
        model(PasswordResetModel::class)->where('id >', 0)->set('expires_at', date('Y-m-d H:i:s', time() - 1))->update();

        $this->postJson('api/auth/reset-password', $this->resetData($this->tokenFromEmail('restablecer-contrasena')))->assertStatus(404);
    }

    public function testValidatesNewPassword(): void
    {
        $this->createUser();
        $this->postJson('api/auth/forgot-password', ['email' => 'ana@example.com']);
        $token = $this->tokenFromEmail('restablecer-contrasena');

        $result = $this->postJson('api/auth/reset-password', ['token' => $token, 'password' => 'corta', 'password_confirm' => 'corta']);

        $result->assertStatus(422);
        $this->assertArrayHasKey('password', $this->json($result)['errors']);
    }

    /**
     * @return array<string, string>
     */
    private function resetData(string $token): array
    {
        return ['token' => $token, 'password' => self::NEW_PASSWORD, 'password_confirm' => self::NEW_PASSWORD];
    }
}
