<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tenant\Gateways\TenantGateway;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantCurrency\TenantCurrencySchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantLanguage\TenantLanguageSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

/*
 * An uninstalled schema must read as "no rows" outside production: the
 * suite holds route tests that never migrate a database, and tenant
 * identification reads the database on every request — so it must not
 * turn each of them into a 500. The condition is created here on purpose,
 * inside the test's own transaction: an in-memory database starts empty,
 * but a real one keeps its schema from test to test. Production gets no
 * such tolerance — the gateway's constructor defaults to it.
 */

uses(RefreshDatabase::class);
uses(HelperTrait::class);

// The module's tables, children before their parent.
beforeEach(function (): void {
    Schema::dropIfExists(TenantDomainSchema::TABLE);
    Schema::dropIfExists(TenantMembershipSchema::TABLE);
    Schema::dropIfExists(TenantLanguageSchema::TABLE);
    Schema::dropIfExists(TenantCurrencySchema::TABLE);
    Schema::dropIfExists(TenantSchema::TABLE);
});

it('answers no rows while tenancy is not installed, outside production', function () {
    expect(Schema::hasTable(TenantSchema::TABLE))->toBeFalse();

    $gateway = new TenantGateway('testing');

    expect($gateway->resolveByDomain('localhost'))->toBeNull()
        ->and($gateway->findBySlug('default'))->toBeNull()
        ->and($gateway->isMembershipActive(1, 1))->toBeFalse()
        ->and($gateway->membershipsFor(1))->toBe([]);

    // The built-in default stands in exactly as it does on an empty table.
    expect($gateway->defaultTenant()?->id)->toBe(TenantGateway::BUILT_IN_DEFAULT_ID)
        ->and($gateway->find(TenantGateway::BUILT_IN_DEFAULT_ID)?->slug)->toBe('default');
});

it('keeps serving requests there as the built-in default, the way an empty table does', function () {
    $this->registerProbeRoutes();

    $this->getJson($this->probeContext())
        ->assertStatus(200)
        ->assertJsonPath('tenant_id', TenantGateway::BUILT_IN_DEFAULT_ID);
});

it('forgives nothing else: with the tenants table present, a missing domain table is raised', function () {
    Schema::create(TenantSchema::TABLE, function (Blueprint $table): void {
        $table->id();
    });

    expect(fn () => (new TenantGateway('testing'))->resolveByDomain('localhost'))->toThrow(QueryException::class);
});

it('raises a missing schema in production instead of resolving a store', function () {
    expect(fn () => (new TenantGateway('production'))->resolveByDomain('localhost'))->toThrow(QueryException::class)
        ->and(fn () => (new TenantGateway('production'))->find(TenantGateway::BUILT_IN_DEFAULT_ID))->toThrow(QueryException::class);
});
