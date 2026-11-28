<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Tenant\Actions\Admin\Membership\RemoveMemberAction;
use Modules\Tenant\Exceptions\Membership\LastOwnerException;
use Modules\Tenant\Exceptions\Membership\MembershipChangeFailedException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMembership;
use Modules\Tenant\Schemas\TenantMembership\TenantMembershipSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

/**
 * Owner-set serialization (multi-tenancy §6.5 Category 2, rev 4.7 B6;
 * §10 row 119). Lives in its own file because RefreshDatabase wraps every
 * test in a transaction: a second connection could never observe the lock.
 * DatabaseMigrations (migrate:fresh, no wrapper) makes real cross-
 * connection blocking observable. PostgreSQL-gated — SQLite has no row
 * locks. The trait rolls the whole schema back at teardown, so this test
 * releases every lock it takes whatever happens.
 */
uses(DatabaseMigrations::class);
uses(HelperTrait::class);

it('serializes competing owner removals on the tenant row; the last active owner is never removed', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row locks need PostgreSQL — run with DB_CONNECTION=pgsql.');
    }

    $owner = User::factory()->active()->create();
    $coOwner = User::factory()->active()->create();
    $member = User::factory()->active()->create();

    $tenant = $this->createTenant('racy');
    $ownerMembership = $this->addMembership($owner, $tenant, owner: true);
    $coMembership = $this->addMembership($coOwner, $tenant, owner: true);
    $this->addMembership($member, $tenant);

    // The third member manages memberships with the seeded owner role.
    $access = app(AccessGatewayInterface::class);
    $ownerRoleId = $access->seedTenantRoles((int) $tenant->getKey());
    $access->assignTenantRole((int) $member->getKey(), (int) $tenant->getKey(), $ownerRoleId);

    config(['database.connections.owner_set_second' => config('database.connections.'.config('database.default'))]);
    $second = DB::connection('owner_set_second');

    // A genuinely separate PDO, not the default connection's.
    expect($second->getPdo())->not->toBe(DB::connection()->getPdo());

    // Connection one holds the tenant row (uncommitted) — as an owner-set
    // mutation would while it checks and changes.
    $second->beginTransaction();
    Tenant::on('owner_set_second')->whereKey($tenant->getKey())->lockForUpdate()->first();

    try {
        // The competing removal must contend on the tenant row BEFORE its
        // check-and-change: a bounded lock wait proves the serialization.
        DB::statement("SET lock_timeout = '1s'");

        app(TenantContext::class)->runForTenant((int) $tenant->getKey(), function () use ($ownerMembership): void {
            expect(fn () => app(RemoveMemberAction::class)->handle((string) $ownerMembership->getKey()))
                ->toThrow(MembershipChangeFailedException::class);
        });

        DB::statement('SET lock_timeout = 0');
        $second->commit();

        // With the lock released, the removal runs — and the invariant
        // holds: exactly one active owner remains (the co-owner), because
        // the removal's own check-and-change serialized on the row.
        app(TenantContext::class)->runForTenant((int) $tenant->getKey(), function () use ($ownerMembership, $coMembership): void {
            app(RemoveMemberAction::class)->handle((string) $ownerMembership->getKey());

            // The competing mutation that arrives after the first one
            // committed re-checks under the held row: removing the now-last
            // active owner is rejected, never silently emptied.
            expect(fn () => app(RemoveMemberAction::class)->handle((string) $coMembership->getKey()))
                ->toThrow(LastOwnerException::class);
        });

        $remainingOwners = TenantMembership::query()
            ->where(TenantMembershipSchema::TENANT_ID, $tenant->getKey())
            ->where(TenantMembershipSchema::IS_OWNER, true)
            ->where(TenantMembershipSchema::STATUS, TenantMembershipSchema::STATUS_ACTIVE)
            ->pluck(TenantMembershipSchema::USER_ID);

        expect($remainingOwners)->toHaveCount(1)
            ->and((int) $remainingOwners[0])->toBe((int) $coOwner->getKey());
    } finally {
        if ($second->transactionLevel() > 0) {
            $second->rollBack();
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::statement('SET lock_timeout = 0');
    }
});
