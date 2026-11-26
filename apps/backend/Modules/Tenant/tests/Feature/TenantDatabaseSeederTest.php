<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Database\Seeders\TenantDatabaseSeeder;
use Modules\Tenant\Gateways\TenantGateway;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('seeds the default tenant, its loopback domains, and an owner membership for each staff account', function () {
    $admin = $this->superAdminUser();
    $customer = $this->plainUser();

    $this->seed(TenantDatabaseSeeder::class);

    $default = Tenant::query()->where(TenantSchema::SLUG, 'default')->firstOrFail();

    expect($default->{TenantSchema::STATUS})->toBe(TenantSchema::STATUS_ACTIVE)
        ->and($default->domains()->pluck(TenantDomainSchema::DOMAIN)->all())->toBe(['localhost', '127.0.0.1'])
        ->and($default->domains()->where(TenantDomainSchema::IS_PRIMARY, true)->count())->toBe(1)
        ->and(TenantMembership::query()->where(TenantMembershipSchema::TENANT_ID, $default->getKey())->pluck(TenantMembershipSchema::USER_ID)->all())
        ->toBe([$admin->getKey()])
        ->and(TenantMembership::query()->where(TenantMembershipSchema::IS_OWNER, true)->count())->toBe(1);

    // A customer is a customer: no store, no membership.
    expect(TenantMembership::query()->where(TenantMembershipSchema::USER_ID, $customer->getKey())->exists())->toBeFalse();

    // "Single-store by zero config": a production gateway resolves the default store from the seed.
    expect((new TenantGateway('production'))->defaultTenant()?->slug)->toBe('default')
        ->and((new TenantGateway('production'))->resolveByDomain('localhost')?->id)->toBe((int) $default->getKey());
});

it('is idempotent, never overwrites an admin change, and restores the loopback domains', function () {
    $this->superAdminUser();

    $this->seed(TenantDatabaseSeeder::class);

    Tenant::query()->where(TenantSchema::SLUG, 'default')->update([TenantSchema::NAME => 'Renamed by the owner']);
    TenantDomain::query()->where(TenantDomainSchema::DOMAIN, '127.0.0.1')->delete();

    $this->seed(TenantDatabaseSeeder::class);

    // The store's name is the owner's; a missing loopback domain comes
    // back — without it the dev storefront would stop resolving.
    expect(Tenant::query()->count())->toBe(1)
        ->and(Tenant::query()->value(TenantSchema::NAME))->toBe('Renamed by the owner')
        ->and(TenantDomain::query()->pluck(TenantDomainSchema::DOMAIN)->all())->toBe(['localhost', '127.0.0.1'])
        ->and(TenantMembership::query()->count())->toBe(1);
});

it('fails closed in production when the default tenant was never seeded', function () {
    expect((new TenantGateway('production'))->defaultTenant())->toBeNull()
        ->and((new TenantGateway('production'))->find(TenantGateway::BUILT_IN_DEFAULT_ID))->toBeNull();
});
