<?php

namespace Tests\Feature;

use App\Models\InvitationModel;
use Tests\Support\ApiTestCase;

/**
 * @internal
 */
final class InvitationApiTest extends ApiTestCase
{
    public function testRequiresSession(): void
    {
        $this->get('api/invitations')->assertStatus(401);
        $this->postJson('api/invitations', ['email' => 'bea@example.com'])->assertStatus(401);
    }

    public function testSendsInvitationByEmail(): void
    {
        $ana = $this->createUser();

        $result = $this->actingAs($ana)->postJson('api/invitations', ['email' => ' Bea@Example.com ']);

        $result->assertStatus(201);
        $this->assertSame(9, $this->json($result)['remaining']);
        $this->assertCount(1, $this->email->sent);
        $this->assertSame(['bea@example.com'], $this->email->sent[0]['to']);
        $this->assertStringContainsString('Ana te invita', $this->email->sent[0]['subject']);

        $token = $this->tokenFromEmail('registro');
        $row   = model(InvitationModel::class)->first();
        $this->assertSame(hash('sha256', $token), $row['token_hash'], 'Solo se guarda el hash del token');
    }

    public function testResendingRenewsTokenWithoutUsingQuota(): void
    {
        $ana = $this->createUser();
        $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com'])->assertStatus(201);
        $first = $this->tokenFromEmail('registro');

        $result = $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com']);

        $result->assertOK();
        $this->assertSame(9, $this->json($result)['remaining']);
        $this->get("api/invitations/{$first}")->assertStatus(404);
        $this->get('api/invitations/' . $this->tokenFromEmail('registro'))->assertOK();
    }

    public function testCannotInviteExistingUser(): void
    {
        $ana = $this->createUser();
        $this->createUser('bea@example.com', 'Bea');

        $result = $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com']);

        $result->assertStatus(422);
        $this->assertSame([], $this->email->sent);
    }

    public function testQuotaIsEnforced(): void
    {
        config('Tuentidad')->invitationQuota = 1;
        $ana                                 = $this->createUser();
        $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com'])->assertStatus(201);

        $result = $this->actingAs($ana)->postJson('api/invitations', ['email' => 'carlos@example.com']);

        $result->assertStatus(422);
        $this->assertSame('Ya has usado todas tus invitaciones.', $this->json($result)['errors']['email']);
    }

    public function testFailedEmailDoesNotUseQuota(): void
    {
        $ana                      = $this->createUser();
        $this->email->returnValue = false;

        $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com'])->assertStatus(502);

        $this->assertSame(0, model(InvitationModel::class)->countAllResults());
    }

    public function testListsOwnInvitationsWithStatus(): void
    {
        $ana    = $this->createUser();
        $model  = model(InvitationModel::class);
        $future = date('Y-m-d H:i:s', time() + DAY);
        $past   = date('Y-m-d H:i:s', time() - DAY);
        $model->insert(['inviter_id' => $ana->id, 'email' => 'pendiente@example.com', 'token_hash' => str_repeat('a', 64), 'expires_at' => $future]);
        $model->insert(['inviter_id' => $ana->id, 'email' => 'caducada@example.com', 'token_hash' => str_repeat('b', 64), 'expires_at' => $past]);
        $model->insert(['inviter_id' => $ana->id, 'email' => 'aceptada@example.com', 'token_hash' => str_repeat('c', 64), 'expires_at' => $future, 'accepted_at' => $past]);
        $model->insert(['inviter_id' => null, 'email' => 'ajena@example.com', 'token_hash' => str_repeat('d', 64), 'expires_at' => $future]);

        $json = $this->json($this->actingAs($ana)->get('api/invitations'));

        $this->assertSame(7, $json['remaining']);
        $statuses = array_column($json['invitations'], 'status', 'email');
        ksort($statuses);
        $this->assertSame(['aceptada@example.com' => 'accepted', 'caducada@example.com' => 'expired', 'pendiente@example.com' => 'pending'], $statuses);
        $this->assertArrayNotHasKey('ajena@example.com', $statuses);
    }

    public function testShowsValidInvitation(): void
    {
        $ana = $this->createUser();
        $this->actingAs($ana)->postJson('api/invitations', ['email' => 'bea@example.com']);
        $token = $this->tokenFromEmail('registro');
        $this->resetServices();
        $_SESSION = [];
        $this->mockSession();

        $result = $this->get("api/invitations/{$token}");

        $result->assertOK();
        $this->assertSame(['email' => 'bea@example.com', 'inviter' => 'Ana Pruebas'], $this->json($result));
    }

    public function testInvalidOrExpiredInvitationsAreNotFound(): void
    {
        $ana = $this->createUser();
        model(InvitationModel::class)->insert([
            'inviter_id' => $ana->id,
            'email'      => 'bea@example.com',
            'token_hash' => hash('sha256', str_repeat('e', 64)),
            'expires_at' => date('Y-m-d H:i:s', time() - 1),
        ]);

        $this->get('api/invitations/' . str_repeat('e', 64))->assertStatus(404);
        $this->get('api/invitations/' . str_repeat('f', 64))->assertStatus(404);
        $this->get('api/invitations/no-es-un-token')->assertStatus(404);
    }
}
