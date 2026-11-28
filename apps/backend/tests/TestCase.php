<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Payment\PaymentCredentialResolverInterface;
use Modules\Core\Models\Language;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Database\Seeders\TenantDatabaseSeeder;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * The default English language row, shared by every module whose
     * translation payloads resolve their `language` codes against it.
     */
    public static function defaultLanguage(): Language
    {
        return Language::query()->firstOrCreate(
            ['code' => 'en'],
            ['locale' => 'en', 'name' => 'English', 'code3' => 'eng', 'is_default' => true, 'is_active' => true],
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Payment credential resolver (marketplace P5.2): its env
        // baseline is a process-level snapshot and the encrypted
        // credential map is cached — without this reset, one test's
        // config(...) seeding (every Webhook/CreatePayment test does it)
        // would leak into the next through either. Reset BEFORE the
        // test body runs, so a test's own config seeding is honored.
        app(PaymentCredentialResolverInterface::class)->refreshEnvBaseline();

        // The tenant context is a process-level singleton (multi-tenancy
        // §2). Pest runs every test in one process, so without this reset
        // a tenant set by one test would leak into the next.
        app(TenantContext::class)->reset();
        // Phase 2: keep the legacy suite (which creates store-owned rows
        // without an explicit tenant) working by defaulting to the built-in
        // default tenant. Tests that exercise the fail-closed path deactivate
        // the context themselves (multi-tenancy §10 row 12). Tests that seed
        // a real default row must point the context at it (see
        // withDefaultStoreMembership): the permission team follows this
        // context (TenantTeamResolver, §5), so pivot writes and request
        // resolution must agree on the id.
        app(TenantContext::class)->forTenant(1);

        // Phase 3 (§5): the spatie permission team IS the tenant context
        // (TenantTeamResolver) — role pivots are NOT NULL on tenant_id, so
        // every assignRole in the suite carries the store the context
        // points at. Tests that exercise the fail-closed path deactivate
        // the context themselves (multi-tenancy §10 row 12).

        // The spatie permission cache is process-wide while every test's
        // rows live in a rolled-back transaction: a permission row cached
        // by one test must not answer another's check. Cold cache per test.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Grants an active membership in the default store (multi-tenancy
     * §3.2): the admin authorization boundary demands one of every
     * non-super-admin actor. Legacy admin-API tests whose actor was only
     * ever "a user with a role" call this to become a real store admin.
     */
    protected function withDefaultStoreMembership(User $user): void
    {
        // Outside production the built-in default snapshot resolves
        // without a row, but memberships FK the tenants table — so the
        // row is seeded here exactly as TenantDatabaseSeeder does.
        $tenantId = (int) Tenant::query()->firstOrCreate(
            [TenantSchema::SLUG => TenantDatabaseSeeder::DEFAULT_SLUG],
            [
                TenantSchema::NAME => 'Default Store',
                TenantSchema::EMAIL => 'store@localhost',
                TenantSchema::STATUS => TenantSchema::STATUS_ACTIVE,
            ],
        )->getKey();

        app(TenantContext::class)->runForTenant($tenantId, function () use ($user): void {
            TenantMembership::query()->create([
                TenantMembershipSchema::USER_ID => $user->getKey(),
                TenantMembershipSchema::STATUS => TenantMembershipSchema::STATUS_ACTIVE,
                TenantMembershipSchema::IS_OWNER => true,
                TenantMembershipSchema::JOINED_AT => now(),
            ]);
        });

        // The permission team follows the context (§5): keep the rest of
        // the test on the row the fallback-host resolution will find.
        app(TenantContext::class)->forTenant($tenantId);
    }
}
