<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

beforeEach(function () {
    $this->actingAs($this->superAdminUser(), 'sanctum');
});

it('attaches a domain as pending, lists it, and releases it into cooling-off', function () {
    $acme = $this->createTenant('acme');
    $base = $this->baseUrl('/admin/tenants/'.$acme->getKey().'/domains');

    $this->postJson($base, ['domain' => 'shop-acme.test', 'is_primary' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.domain', 'shop-acme.test')
        // Inert until verified: it resolves nothing yet.
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.is_primary', true);

    // A store that already has a primary keeps it: switching the primary
    // belongs to domain verification.
    $this->postJson($base, ['domain' => 'shop-acme-2.test', 'is_primary' => true])
        ->assertStatus(201)
        ->assertJsonPath('data.is_primary', false);

    $this->getJson($base)->assertOk()->assertJsonCount(2, 'data');
    $domainId = (string) TenantDomain::query()->where(TenantDomainSchema::DOMAIN, 'shop-acme.test')->value(TenantDomainSchema::ID);

    $this->deleteJson($base.'/'.$domainId)->assertNoContent();

    $released = TenantDomain::query()->where(TenantDomainSchema::DOMAIN, 'shop-acme.test')->firstOrFail();

    expect($released->{TenantDomainSchema::STATUS})->toBe(TenantDomainSchema::STATUS_RELEASED)
        ->and($released->{TenantDomainSchema::RELEASED_AT})->not->toBeNull()
        ->and($released->{TenantDomainSchema::COOLING_OFF_UNTIL}?->diffInDays(now(), true))->toBeGreaterThan(6.99)
        ->and($released->{TenantDomainSchema::IS_PRIMARY})->toBeFalse();

    // Releasing again changes nothing — and leaves the other domain alone.
    $this->deleteJson($base.'/'.$domainId)->assertNoContent();

    expect(TenantDomain::query()->count())->toBe(2)
        ->and(TenantDomain::query()->where(TenantDomainSchema::DOMAIN, 'shop-acme-2.test')->value(TenantDomainSchema::STATUS))
        ->toBe(TenantDomainSchema::STATUS_PENDING);
});

it('refuses a domain held by another store, and one still cooling off', function () {
    $acme = $this->createTenant('acme');
    $other = $this->createTenant('other');
    $forAcme = $this->baseUrl('/admin/tenants/'.$acme->getKey().'/domains');
    $forOther = $this->baseUrl('/admin/tenants/'.$other->getKey().'/domains');

    $this->postJson($forAcme, ['domain' => 'shop.test'])->assertStatus(201);

    // Held by another store.
    $this->postJson($forOther, ['domain' => 'shop.test'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'tenant.domain.in_use');

    // Already this store's own.
    $this->postJson($forAcme, ['domain' => 'shop.test'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'tenant.domain.already_attached');

    $this->deleteJson($forAcme.'/'.TenantDomain::query()->value(TenantDomainSchema::ID))->assertNoContent();

    // Released, but inside its cooling-off window: no other store may
    // attach it — the same store may take it back.
    $this->postJson($forOther, ['domain' => 'shop.test'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'tenant.domain.cooling_off');

    // Once the window has passed, the domain is free to move.
    TenantDomain::query()->update([TenantDomainSchema::COOLING_OFF_UNTIL => now()->subMinute()]);

    $this->postJson($forOther, ['domain' => 'shop.test'])->assertStatus(201);

    expect(TenantDomain::query()->where(TenantDomainSchema::TENANT_ID, $other->getKey())->count())->toBe(1);
});

it('rejects a domain that is not a hostname', function () {
    $acme = $this->createTenant('acme');

    foreach (['not a domain', 'https://shop.test', 'shop..test', '-shop.test'] as $domain) {
        $this->postJson($this->baseUrl('/admin/tenants/'.$acme->getKey().'/domains'), ['domain' => $domain])
            ->assertStatus(422);
    }

    expect(TenantDomain::query()->count())->toBe(0);
});

it('stops resolving a domain once it is released, and a store once it is deleted', function () {
    // No loopback fallback here: an unresolved host must be a clean 404.
    $this->productionLikeTenancy();
    $this->registerProbeRoutes();
    $acme = $this->createTenant('acme', 'shop-acme.test');

    $this->getJson($this->onHost('shop-acme.test', $this->probeContext()))->assertStatus(200);

    $this->deleteJson($this->baseUrl('/admin/tenants/'.$acme->getKey().'/domains/'.$acme->domains()->first()->getKey()))
        ->assertNoContent();

    $this->getJson($this->onHost('shop-acme.test', $this->probeContext()))
        ->assertStatus(404)
        ->assertJsonPath('meta.reason', 'unknown_host');

    // A verified domain of a soft-deleted store resolves nothing either.
    $other = $this->createTenant('other', 'shop-other.test');
    $this->getJson($this->onHost('shop-other.test', $this->probeContext()))->assertStatus(200);

    $this->deleteJson($this->baseUrl('/admin/tenants/'.$other->getKey()))->assertNoContent();

    $this->getJson($this->onHost('shop-other.test', $this->probeContext()))->assertStatus(404);

    // Each release and deletion ran as a central operation.
    expect(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);
});
