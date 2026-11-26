<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Audit\Models\AuditLog;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Actions\User\Onboarding\ProvisionTenantAction;
use Modules\Tenant\Exceptions\Tenant\SlugTakenException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantDomain;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantDomain\TenantDomainSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

uses(RefreshDatabase::class);
uses(HelperTrait::class);

it('provisions a store for an authenticated user from a central host, atomically and without central mode', function () {
    $this->productionLikeTenancy();
    $user = $this->plainUser();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson($this->onHost('api.example.test', '/api/v1/onboarding/tenants'), [
            'name' => 'My Store',
            'slug' => 'mystore',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.slug', 'mystore');

    $tenant = Tenant::query()->findOrFail($response->json('data.id'));
    $domain = TenantDomain::query()->where(TenantDomainSchema::TENANT_ID, $tenant->getKey())->firstOrFail();
    $membership = TenantMembership::query()->where(TenantMembershipSchema::TENANT_ID, $tenant->getKey())->firstOrFail();

    expect($domain->{TenantDomainSchema::DOMAIN})->toBe('mystore.localhost')
        ->and($domain->{TenantDomainSchema::STATUS})->toBe(TenantDomainSchema::STATUS_VERIFIED)
        ->and($domain->{TenantDomainSchema::IS_PRIMARY})->toBeTrue()
        ->and($membership->{TenantMembershipSchema::USER_ID})->toBe($user->getKey())
        ->and($membership->{TenantMembershipSchema::IS_OWNER})->toBeTrue()
        ->and($membership->{TenantMembershipSchema::STATUS})->toBe(TenantMembershipSchema::STATUS_ACTIVE)
        // The store's contact address starts as its owner's.
        ->and($tenant->getAttribute(TenantSchema::EMAIL))->toBe($user->getAttribute(UserSchema::EMAIL));

    // Central mode was never entered: no central operation ran, and the
    // request context is what the tenant-neutral audience leaves behind.
    expect(AuditLog::query()->where(AuditLogSchema::ENTITY, 'central_operation_run')->count())->toBe(0)
        ->and(app(TenantContext::class)->mode())->toBe(TenantContext::MODE_INACTIVE);

    // Creating a store changes the platform, so it is on the record.
    $recorded = (array) AuditLog::query()->where(AuditLogSchema::ENTITY, 'tenant_provisioned')->sole()->{AuditLogSchema::PROPERTIES};

    expect($recorded['id'])->toBe((int) $tenant->getKey())
        ->and($recorded['slug'])->toBe('mystore')
        ->and($recorded['owner_id'])->toBe($user->getKey());
});

it('needs no existing store or membership — only an authenticated user', function () {
    $this->productionLikeTenancy();

    $this->postJson($this->onHost('api.example.test', '/api/v1/onboarding/tenants'), [
        'name' => 'My Store',
        'slug' => 'mystore',
    ])->assertStatus(401);

    expect(Tenant::query()->count())->toBe(0);
});

it('enforces the per-user store limit', function () {
    $this->actingAs($this->plainUser(), 'sanctum');
    $limit = (int) config('core.tenancy.onboarding.max_tenants_per_user');

    for ($i = 1; $i <= $limit; $i++) {
        $this->postJson('/api/v1/onboarding/tenants', ['name' => 'Store '.$i, 'slug' => 'store-'.$i])
            ->assertStatus(201);
    }

    $this->postJson('/api/v1/onboarding/tenants', ['name' => 'One too many', 'slug' => 'one-too-many'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'tenant.onboarding.limit_reached')
        ->assertJsonPath('meta.limit', $limit);

    expect(Tenant::query()->count())->toBe($limit);
});

it('refuses a taken slug', function () {
    $this->actingAs($this->plainUser(), 'sanctum');

    $this->postJson('/api/v1/onboarding/tenants', ['name' => 'My Store', 'slug' => 'mystore'])->assertStatus(201);

    // Validation answers first…
    $this->postJson('/api/v1/onboarding/tenants', ['name' => 'Copycat', 'slug' => 'mystore'])
        ->assertStatus(422);

    // …and the action resolves the race on the unique index.
    expect(fn () => app(ProvisionTenantAction::class)->handle($this->plainUser(), [
        'name' => 'Copycat',
        'slug' => 'mystore',
    ]))->toThrow(SlugTakenException::class)
        ->and(Tenant::query()->count())->toBe(1);
});

it('leaves nothing behind when provisioning fails part-way', function () {
    $user = $this->plainUser();

    // The subdomain's uniqueness is the constraint that fails last: the
    // store row and the subdomain are written before the membership.
    config(['core.tenancy.onboarding.subdomain_base' => 'localhost']);
    TenantDomain::query()->create([
        TenantDomainSchema::TENANT_ID => $this->createTenant('other')->getKey(),
        TenantDomainSchema::DOMAIN => 'clash.localhost',
        TenantDomainSchema::STATUS => TenantDomainSchema::STATUS_VERIFIED,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/onboarding/tenants', ['name' => 'Clash', 'slug' => 'clash'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'tenant.domain.in_use');

    expect(Tenant::query()->where(TenantSchema::SLUG, 'clash')->doesntExist())->toBeTrue()
        ->and(TenantMembership::query()->where(TenantMembershipSchema::USER_ID, $user->getKey())->count())->toBe(0)
        ->and(TenantDomain::query()->where(TenantDomainSchema::DOMAIN, 'clash.localhost')->count())->toBe(1);
});
