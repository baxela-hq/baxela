<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\TransferLock\TransferLockSchema;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Transfer\TransferGuardRegistry;
use Modules\Core\Support\Transfer\TransferImportSession;
use Modules\Core\Support\Transfer\TransferImportTransaction;

/**
 * Lease-row / guard / write-unit semantics (postgres-migration §3.1) on
 * the SQLite test plane: ownership is ROW state (token + generation +
 * live DB-clock lease), so the whole protocol — acquisition, reclamation
 * fencing, renewal, fenced write units — is driver-agnostic and testable
 * here in full. Real-server concurrency semantics (FOR UPDATE blocking,
 * clock_timestamp pinning) run against MySQL/PostgreSQL in Phase D.
 *
 * Deliberately NOT RefreshDatabase: these tests own the connection's
 * transaction lifecycle (begin/commit/rollback, PDO replacement), which
 * would desync RefreshDatabase's outer per-test transaction. A plain
 * per-test migrate gives each test its own unwrapped :memory: database.
 */
beforeEach(function () {
    $this->artisan('migrate:fresh', ['--force' => true]);
});

afterEach(function () {
    // The F33 test swaps in a broken foreign PDO; drop it so the next
    // use reconnects to the real configured engine (on the PostgreSQL
    // leg a stale sqlite PDO made the following migrate:fresh run
    // pg_class SQL against sqlite).
    DB::connection()->disconnect();
});

afterEach(function () {
    // Shared-server planes (the PostgreSQL CI leg): leave a clean schema
    // for whatever file runs next — these tests own their lifecycle and
    // run outside RefreshDatabase, so their rows would otherwise leak
    // into later suites' expectations.
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->artisan('migrate:fresh', ['--force' => true]);
    }
});

it('activates the run context during the run and deactivates it after', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $registry = app(TransferGuardRegistry::class);
    $seen = null;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use ($registry, &$seen): void {
        $seen = $context;

        expect($registry->contextFor(DB::connection()))->toBe($context)
            ->and($context->sourceInstallation)->toBe('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')
            ->and($context->identified)->toBeTrue();
    });

    // Teardown ran (F30): the hook reads an empty registry from here on.
    expect($seen)->not->toBeNull()
        ->and($registry->contextFor(DB::connection()))->toBeNull();
});

it('maps legacy files into the unidentified namespace', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $seen = null;

    $session->run('tax', TransferRemapInterface::UNIDENTIFIED, false, function ($context) use (&$seen): void {
        $seen = $context;
    });

    expect($seen->identified)->toBeFalse()
        ->and($seen->sourceInstallation)->toBe('unidentified');
});

it('runs a write unit transactionally and refuses to run outside a run', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));

    // No active run — an importer writing outside the lifecycle is a bug.
    expect(fn () => $transactions->run(fn () => null))->toThrow(RuntimeException::class, 'import_context_missing');

    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $outcome = $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): string {
        return $transactions->run(function (): string {
            DB::table(TransferRemapSchema::TABLE)->insert([
                TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                TransferRemapSchema::MODULE => 'tax',
                TransferRemapSchema::ENTITY => 'tax-classes',
                TransferRemapSchema::SOURCE_ID => 42,
                TransferRemapSchema::LOCAL_ID => 108,
            ]);

            return 'unit-ok';
        });
    });

    expect($outcome)->toBe('unit-ok')
        ->and(DB::table(TransferRemapSchema::TABLE)->count())->toBe(1);
});

it('rolls back the whole unit when a write inside it fails', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): void {
        try {
            $transactions->run(function (): void {
                DB::table(TransferRemapSchema::TABLE)->insert([
                    TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                    TransferRemapSchema::MODULE => 'tax',
                    TransferRemapSchema::ENTITY => 'tax-classes',
                    TransferRemapSchema::SOURCE_ID => 42,
                    TransferRemapSchema::LOCAL_ID => 108,
                ]);

                throw new RuntimeException('row went bad');
            });
        } catch (RuntimeException) {
            // The importer records this as a per-row failure and continues.
        }
    });

    // Entity write + everything else in the unit died together (F11).
    expect(DB::table(TransferRemapSchema::TABLE)->count())->toBe(0);
});

it('aborts ownership when the lease row is reclaimed by another run (F28 successor)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $captured = null;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use (&$captured): void {
        $captured = $context;
    });

    // Simulate a reclaimer: expiry passed, ownership transferred, the
    // fencing generation advanced. The stale run must fail closed.
    reclaimLeaseRow('transfer:import:tax');

    expect(fn () => $captured->assertOwnership())->toThrow(TransferLockSessionLostException::class);
});

it('aborts ownership when the lease merely expires (DB-clock liveness)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $captured = null;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use (&$captured): void {
        $captured = $context;
    });

    // Same token + generation, but the lease is no longer live.
    DB::table(TransferLockSchema::TABLE)
        ->where(TransferLockSchema::NAME, 'transfer:import:tax')
        ->update([TransferLockSchema::EXPIRES_AT => '2000-01-01 00:00:00.000']);

    expect(fn () => $captured->assertOwnership())->toThrow(TransferLockSessionLostException::class);
});

it('fences a stale owner out of protected writes after reclamation (§3.1.1, matrix 17)', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $wrote = false;

    try {
        $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions, &$wrote): void {
            // Mid-run, a reclaimer takes the expired lease (generation+1,
            // new token) BEFORE the next write unit opens.
            backdateLeaseRow('transfer:import:tax');
            reclaimLeaseRow('transfer:import:tax');

            $transactions->run(function () use (&$wrote): void {
                DB::table(TransferRemapSchema::TABLE)->insert([
                    TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                    TransferRemapSchema::MODULE => 'tax',
                    TransferRemapSchema::ENTITY => 'tax-classes',
                    TransferRemapSchema::SOURCE_ID => 42,
                    TransferRemapSchema::LOCAL_ID => 108,
                ]);
                $wrote = true;
            });
        });

        $this->fail('The stale owner should have been fenced out of the write unit.');
    } catch (TransferLockSessionLostException) {
        // Fenced: the row check inside the unit's transaction refused the
        // write BEFORE it executed — nothing was persisted.
    }

    expect($wrote)->toBeFalse()
        ->and(DB::table(TransferRemapSchema::TABLE)->count())->toBe(0);
});

it('refuses renewal to an expired owner (invariant I1, matrix 19)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $captured = null;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use (&$captured): void {
        $captured = $context;
    });

    // The lease expires (post-teardown backdate): the still-known owner
    // token + generation must NOT be enough to renew itself back in.
    backdateLeaseRow('transfer:import:tax');

    expect(fn () => $captured->renewLease())->toThrow(TransferLockSessionLostException::class);
});

it('fails a contended import with import_in_progress past the wait budget (F26)', function () {
    // A foreign, live lease on the same destination module.
    seedForeignLease('transfer:import:tax');

    config(['core.transfer.lock_timeout' => 0]);

    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    try {
        $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);
        $this->fail('The contended import should have failed with import_in_progress.');
    } catch (ImportFailedException $e) {
        expect($e->meta['reason'] ?? null)->toBe('import_in_progress');
    }
});

it('reclaims an expired lease with an advanced generation on the next import (I3)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);

    // Teardown expires the lease; the next import reclaims it and the
    // fencing generation advances exactly once (only path: reclamation).
    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);

    $row = DB::table(TransferLockSchema::TABLE)
        ->where(TransferLockSchema::NAME, 'transfer:import:tax')
        ->first();

    expect((int) $row->{TransferLockSchema::GENERATION})->toBe(2);
});

it('does not reclaim a live lease held by another run (F26)', function () {
    seedForeignLease('transfer:import:tax');

    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    // The foreign lease is live — reclamation must refuse it.
    try {
        $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);
        $this->fail('A live foreign lease must not be reclaimable.');
    } catch (ImportFailedException $e) {
        expect($e->meta['reason'] ?? null)->toBe('import_in_progress');
    }

    $row = DB::table(TransferLockSchema::TABLE)
        ->where(TransferLockSchema::NAME, 'transfer:import:tax')
        ->first();

    // The foreign owner kept its token AND its generation (no increment).
    expect($row->{TransferLockSchema::OWNER_TOKEN})->toBe('11111111-2222-3333-4444-555555555555')
        ->and((int) $row->{TransferLockSchema::GENERATION})->toBe(7);
});

it('intercepts the pending write when the session is lost mid-unit (F29)', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): void {
        $transactions->run(function (): void {
            // The replacement session is an empty :memory: database — any
            // statement that reached it would fail with a missing-table
            // QueryException, NOT lock_session_lost. The exception type
            // therefore proves the unit-boundary check intercepted BEFORE
            // the statement executed (F29: no importer write on a
            // replacement session inside a unit).
            DB::connection()->disconnect();

            DB::table(TransferRemapSchema::TABLE)->insert([
                TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                TransferRemapSchema::MODULE => 'tax',
                TransferRemapSchema::ENTITY => 'tax-classes',
                TransferRemapSchema::SOURCE_ID => 42,
                TransferRemapSchema::LOCAL_ID => 108,
            ]);
        });
    });
})->throws(TransferLockSessionLostException::class);

it('intercepts the pending write on an explicit mid-unit reconnect (F29)', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): void {
        $transactions->run(function (): void {
            // reconnect() replaces the PDO object but keeps the connection
            // object (and its guards) — mid-unit, that swap means the
            // server dropped the unit's transaction; the boundary check
            // must catch exactly this.
            DB::connection()->reconnect();

            DB::table(TransferRemapSchema::TABLE)->insert([
                TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                TransferRemapSchema::MODULE => 'tax',
                TransferRemapSchema::ENTITY => 'tax-classes',
                TransferRemapSchema::SOURCE_ID => 42,
                TransferRemapSchema::LOCAL_ID => 108,
            ]);
        });
    });
})->throws(TransferLockSessionLostException::class);

it('never retries a unit on a replacement session (F31)', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $calls = 0;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions, &$calls): void {
        $transactions->run(function () use (&$calls): void {
            $calls++;

            DB::connection()->disconnect();

            DB::table(TransferRemapSchema::TABLE)->insert([
                TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                TransferRemapSchema::MODULE => 'tax',
                TransferRemapSchema::ENTITY => 'tax-classes',
                TransferRemapSchema::SOURCE_ID => 42,
                TransferRemapSchema::LOCAL_ID => 108,
            ]);
        });
    });

    expect($calls)->toBe(1);
})->throws(TransferLockSessionLostException::class);

it('registers exactly one guard per connection across repeated runs (F32)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $connection = DB::connection();

    $callbacks = (new ReflectionClass($connection))->getProperty('beforeExecutingCallbacks');
    $before = count($callbacks->getValue($connection));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);
    $afterFirst = count($callbacks->getValue($connection));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);
    $afterSecond = count($callbacks->getValue($connection));

    expect($afterFirst)->toBe($before + 1)
        // Register-once: repeated imports accumulate nothing (F32).
        ->and($afterSecond)->toBe($afterFirst);
});

it('keeps lock_session_lost fatal even when the rollback itself fails (F33)', function () {
    Log::shouldReceive('warning')->once()->with(
        'rollback_after_session_loss_failed',
        Mockery::on(fn (array $payload): bool => $payload['lock'] === 'transfer:import:tax')
    );

    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): void {
        $transactions->run(function (): void {
            // setPdo swaps in a replacement PDO that cannot even answer
            // whether a transaction is active. Laravel's setPdo resets the
            // transaction counter, so the unit's level is re-established on
            // the replacement — mirroring a replacement session whose
            // transaction state the connection layer still believes in —
            // and the best-effort rollback after the loss genuinely fails,
            // swallowed without masking the fatal classification.
            DB::connection()->setPdo(new TransferTestBrokenPdo('sqlite::memory:'));
            DB::connection()->beginTransaction();

            DB::table(TransferRemapSchema::TABLE)->insert([
                TransferRemapSchema::SOURCE_INSTALLATION => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                TransferRemapSchema::MODULE => 'tax',
                TransferRemapSchema::ENTITY => 'tax-classes',
                TransferRemapSchema::SOURCE_ID => 42,
                TransferRemapSchema::LOCAL_ID => 108,
            ]);
        });
    });
})->throws(TransferLockSessionLostException::class);

/**
 * Test-local PDO whose transaction probe explodes — the SQLite-plane
 * stand-in for "the session died mid-rollback" (F33). File-scoped class,
 * uniquely named for the single-process Pest runner.
 */
class TransferTestBrokenPdo extends PDO
{
    public function inTransaction(): bool
    {
        throw new PDOException('broken probe');
    }
}

it('leaves the connection guard inert for ordinary queries after teardown', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, fn () => null);

    // A normal query after the run: the hook no-ops on the inactive
    // registry (F31 retain+deactivate lifecycle).
    expect(DB::table(TransferRemapSchema::TABLE)->count())->toBe(0);
});

it('fails fast on a lock name that exceeds the 64-character limit', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    expect(fn () => $session->lockNameFor(str_repeat('a', 60)))->toThrow(ImportFailedException::class)
        ->and($session->lockNameFor('tax'))->toBe('transfer:import:tax');
});

// ---------------------------------------------------------------------------
// Lease-row helpers: direct table surgery standing in for a competitor run.
// ---------------------------------------------------------------------------

/** Seed a live foreign lease (owner + generation 7, far-future expiry). */
function seedForeignLease(string $name): void
{
    DB::table(TransferLockSchema::TABLE)->insert([
        TransferLockSchema::NAME => $name,
        TransferLockSchema::OWNER_TOKEN => '11111111-2222-3333-4444-555555555555',
        TransferLockSchema::GENERATION => 7,
        TransferLockSchema::HEARTBEAT_AT => '2000-01-01 00:00:00.000',
        TransferLockSchema::EXPIRES_AT => '2999-01-01 00:00:00.000',
    ]);
}

/** Push a lease row into the past so reclamation becomes legal. */
function backdateLeaseRow(string $name): void
{
    DB::table(TransferLockSchema::TABLE)
        ->where(TransferLockSchema::NAME, $name)
        ->update([TransferLockSchema::EXPIRES_AT => '2000-01-01 00:00:00.000']);
}

/** Simulate a reclaimer taking the (expired) lease: new token, generation+1. */
function reclaimLeaseRow(string $name): void
{
    DB::table(TransferLockSchema::TABLE)
        ->where(TransferLockSchema::NAME, $name)
        ->update([
            TransferLockSchema::OWNER_TOKEN => '99999999-8888-7777-6666-555555555555',
            TransferLockSchema::GENERATION => DB::raw(TransferLockSchema::GENERATION.' + 1'),
            TransferLockSchema::HEARTBEAT_AT => '2999-01-01 00:00:00.000',
            TransferLockSchema::EXPIRES_AT => '2999-01-01 00:00:00.000',
        ]);
}
