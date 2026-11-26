<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\NewAccessToken;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->productionLikeTenancy();
});

it('resolves the store from a tenant-bound machine token, and lets no header override it', function () {
    $owner = $this->plainUser();
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');

    $this->withToken($this->machineToken($owner, (int) $acme->getKey()))
        ->getJson('/api/v1/webhooks/machine/products')
        ->assertStatus(200);

    expect(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());

    $this->withToken($this->machineToken($owner, (int) $acme->getKey()))
        ->getJson('/api/v1/webhooks/machine/products', [
            'X-Tenant-Id' => (string) $other->getKey(),
            'X-Tenant-Host' => 'shop-other.test',
        ])
        ->assertStatus(200);

    expect(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());
});

it('fails closed for an ordinary token, a revoked one, an expired one, or a wildcard one', function () {
    $owner = $this->plainUser();
    $acme = $this->createTenant('acme');

    $refused = function (string $plain, string $why): void {
        $this->withToken($plain)->getJson('/api/v1/webhooks/machine/products')
            ->assertStatus(404, $why)
            ->assertJsonPath('code', 'core.tenant.not_found')
            ->assertJsonPath('meta.reason', 'invalid_machine_token', $why);
    };

    // An ordinary user token carries no store: it is not a machine token.
    $refused($this->mintMachineToken($owner, null)->plainTextToken, 'an ordinary token is not a machine token');

    // Expired.
    $token = $this->mintMachineToken($owner, (int) $acme->getKey());
    $token->accessToken->forceFill(['expires_at' => now()->subHour()])->save();
    $refused($token->plainTextToken, 'an expired token');

    // Revoked: the row is deleted.
    $token = $this->mintMachineToken($owner, (int) $acme->getKey());
    $plain = $token->plainTextToken;
    $token->accessToken->delete();
    $refused($plain, 'a revoked token');

    // A wildcard token is refused even when a store was bound to it.
    /** @var NewAccessToken $token */
    $token = $owner->createToken('wildcard', ['*']);
    $token->accessToken->forceFill([TenantColumn::TENANT_ID => $acme->getKey()])->save();
    $refused($token->plainTextToken, 'a wildcard token');

    // Nothing of the refused requests remains.
    expect(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('is denied for a suspended store before any store-scoped work', function () {
    $owner = $this->plainUser();
    $suspended = $this->createTenant('acme', status: TenantSchema::STATUS_SUSPENDED);

    $this->withToken($this->machineToken($owner, (int) $suspended->getKey()))
        ->getJson('/api/v1/webhooks/machine/products')
        ->assertStatus(403)
        ->assertJsonPath('code', 'core.tenant.suspended');

    expect(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);
});

it('binds the issuing admin\'s store at mint', function () {
    $admin = $this->superAdminUser();
    $owner = $this->plainUser();
    $acme = $this->createTenant('acme');

    // A real bearer token, not actingAs(): that would leave the admin on
    // the guard, so the request below would not rely on the minted token.
    $adminToken = $admin->createToken('admin-session')->plainTextToken;

    $minted = $this->withToken($adminToken)
        ->postJson('/api/v1/auth/admin/machine-tokens', [
            'name' => 'integration',
            'user_id' => $owner->getKey(),
            'abilities' => ['products.read'],
            'expires_days' => 30,
        ], ['X-Tenant-Id' => (string) $acme->getKey()])
        ->assertStatus(201);

    expect($minted->json('data.tenant_id'))->toBe((int) $acme->getKey());

    // The token the endpoint minted works on the machine routes — for that store.
    $this->withToken($minted->json('data.token'))
        ->getJson('/api/v1/webhooks/machine/products')
        ->assertStatus(200);

    expect(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());
});
