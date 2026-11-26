<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Models\User;
use Modules\Tenant\Actions\User\Onboarding\ProvisionTenantAction;
use Modules\Tenant\Exceptions\Onboarding\ProvisioningFailedException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Schemas\Tenant\TenantSchema;
use Modules\Tenant\Tests\Feature\HelperTrait;

/**
 * The onboarding quota is checked under a row lock on the user (multi-
 * tenancy §6.5). Lives in its own file because RefreshDatabase wraps every
 * test in a transaction: a second connection could never observe the lock.
 * DatabaseMigrations (migrate:fresh, no wrapper) makes real cross-
 * connection blocking observable. PostgreSQL-gated — SQLite has no row
 * locks. The trait rolls the whole schema back at teardown, so this test
 * releases every lock it takes whatever happens: a failure that left one
 * held would make that rollback fail and leave no tables for every test
 * after it.
 */
uses(DatabaseMigrations::class);
uses(HelperTrait::class);

it('blocks a second onboarding of the same user while the first holds the row lock', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Row locks need PostgreSQL — run with DB_CONNECTION=pgsql.');
    }

    $user = User::factory()->active()->create();

    config(['database.connections.tenant_quota_second' => config('database.connections.'.config('database.default'))]);
    $second = DB::connection('tenant_quota_second');

    // A genuinely separate PDO, not the default connection's.
    expect($second->getPdo())->not->toBe(DB::connection()->getPdo());

    // The first onboarding holds the user's row and stays uncommitted.
    $second->beginTransaction();
    User::on('tenant_quota_second')->whereKey($user->getKey())->lockForUpdate()->first();

    try {
        // The second must contend on that row: a bounded lock wait proves
        // the quota check is serialized on the user. To the caller that is
        // a failed onboarding — never a partial store — and the lock is
        // what was reported (PostgreSQL's lock_not_available).
        DB::statement("SET lock_timeout = '1s'");

        $this->spy(ExceptionHandler::class)->shouldReceive('report')->once()->withArgs(
            fn (QueryException $e): bool => str_contains($e->getMessage(), '55P03'),
        );

        $payload = ['name' => 'Racing Store', 'slug' => 'racing-store'];

        expect(fn () => app(ProvisionTenantAction::class)->handle($user, $payload))
            ->toThrow(ProvisioningFailedException::class);

        expect(Tenant::query()->count())->toBe(0);

        // The first releases the row; the second completes normally.
        $second->commit();
        DB::statement('SET lock_timeout = 0');

        $tenant = app(ProvisionTenantAction::class)->handle($user, $payload);

        expect($tenant->{TenantSchema::SLUG})->toBe('racing-store')
            ->and(Tenant::query()->count())->toBe(1);
    } finally {
        // Release the row and the timeout whatever happened above — see
        // the file note.
        if ($second->transactionLevel() > 0) {
            $second->rollBack();
        }

        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        DB::statement('SET lock_timeout = 0');
    }
});
