<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->registerProbeRoutes();
    $this->productionLikeTenancy();
});

it('fails closed on an unknown host under a production-like config', function () {
    $this->getJson($this->onHost('unknown.example.test', $this->probeContext()))
        ->assertStatus(404)
        ->assertJsonPath('code', 'core.tenant.not_found')
        ->assertJsonPath('meta.reason', 'unknown_host');
});

it('resolves a storefront by its verified domain, normalized', function () {
    $acme = $this->createTenant('acme', 'shop-acme.test');

    $this->getJson($this->onHost('SHOP-ACME.test:8443', $this->probeContext()))
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', (int) $acme->getKey());
});

it('resolves the default tenant for loopback hosts', function () {
    config(['core.tenancy.fallback_hosts' => 'localhost,127.0.0.1']);

    $default = $this->createTenant('default');

    $this->getJson($this->onHost('localhost', $this->probeContext()))
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', (int) $default->getKey());
});

it('honors the forwarded storefront host on public routes and ignores X-Tenant-Id there', function () {
    $acme = $this->createTenant('acme', 'shop-acme.test');
    $other = $this->createTenant('other', 'shop-other.test');

    // The storefront-forwarded host selects the public store being browsed.
    $this->getJson($this->onHost('api.example.test', $this->probeContext()), ['X-Tenant-Host' => 'shop-other.test'])
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', (int) $other->getKey());

    // X-Tenant-Id is not trusted on a public route: the host identity wins.
    $this->getJson($this->onHost('shop-acme.test', $this->probeContext()), ['X-Tenant-Id' => (string) $other->getKey()])
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', (int) $acme->getKey());
});

it('closes a suspended storefront', function () {
    $this->createTenant('acme', 'shop-acme.test', TenantSchema::STATUS_SUSPENDED);

    $this->getJson($this->onHost('shop-acme.test', $this->probeContext()))
        ->assertStatus(403)
        ->assertJsonPath('code', 'core.tenant.suspended');
});

it('takes the tenant from the webhook route slug, ahead of any header', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');

    Route::middleware('api')->name('api.tenant.webhook.probe')
        ->post('/api/v1/tenant-probe/webhook/{tenantSlug}', fn () => [
            'tenant_id' => app(TenantContext::class)->tenantId(),
        ]);

    $this->postJson('/api/v1/tenant-probe/webhook/acme', [], [
        'X-Tenant-Id' => (string) $other->getKey(),
        'X-Tenant-Host' => 'shop-other.test',
    ])
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', (int) $acme->getKey());

    $this->postJson($this->onHost('api.example.test', '/api/v1/tenant-probe/webhook/nobody'))
        ->assertStatus(404)
        ->assertJsonPath('meta.reason', 'unknown_webhook_slug');
});

it('lets an admin name the store by header on a central host', function () {
    $this->actingAs($this->superAdminUser(), 'sanctum');
    $acme = $this->createTenant('acme');

    $this->getJson($this->onHost('api.example.test', '/'.$this->baseUrl('/admin/tenants')), ['X-Tenant-Id' => (string) $acme->getKey()])
        ->assertStatus(200);

    expect(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());
});

it('selects the store for an admin on a central host from their memberships', function () {
    $staff = $this->plainUser();
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');

    // No membership at all: refused before any permission is consulted.
    $this->actingAs($staff, 'sanctum')
        ->getJson($this->onHost('api.example.test', '/'.$this->baseUrl('/admin/tenants')))
        ->assertStatus(403)
        ->assertJsonPath('code', 'core.tenant.membership_required');

    // Exactly one active membership: that store is selected for the request.
    $this->addMembership($staff, $acme);

    $this->getJson($this->onHost('api.example.test', '/'.$this->baseUrl('/admin/tenants')))
        ->assertStatus(403);

    expect(app(TenantContext::class)->tenantId())->toBe((int) $acme->getKey());

    // Several memberships: the caller must name the store.
    $this->addMembership($staff, $other);

    $this->getJson($this->onHost('api.example.test', '/'.$this->baseUrl('/admin/tenants')))
        ->assertStatus(422)
        ->assertJsonPath('code', 'core.tenant.selection_required')
        ->assertJsonPath('meta.memberships', ['acme', 'other']);
});

it('lets an unauthenticated admin request reach its own auth middleware on a central host', function () {
    $this->getJson($this->onHost('api.example.test', '/'.$this->baseUrl('/admin/tenants')))
        ->assertStatus(401)
        ->assertJsonPath('code', 'http.401');

    expect(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);
});
