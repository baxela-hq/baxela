<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;
use Modules\Core\Support\Transfer\TransferGuardRegistry;
use Modules\Core\Support\Transfer\TransferImportSession;
use Modules\Core\Support\Transfer\TransferImportTransaction;
use PDO;

/**
 * Session / guard / write-unit semantics on the SQLite test plane: named
 * locks don't exist here, so the run context proves session identity by
 * PDO instance — which catches exactly the disconnect() and reconnect()
 * replacement signals the MySQL probes catch server-side. The MySQL-only
 * named-lock behaviors live in TransferImportMysqlLockTest.
 *
 * Deliberately NOT RefreshDatabase: these tests own the connection's
 * transaction lifecycle (begin/commit/rollback, PDO replacement), which
 * would desync RefreshDatabase's outer per-test transaction. A plain
 * per-test migrate gives each test its own unwrapped :memory: database.
 */
beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
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

it('aborts ownership between units when the session is lost (F28)', function () {
    $session = new TransferImportSession(app(TransferGuardRegistry::class));
    $captured = null;

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function ($context) use (&$captured): void {
        $captured = $context;
    });

    DB::connection()->disconnect();

    expect(fn () => $captured->assertOwnership())->toThrow(TransferLockSessionLostException::class);
});

it('intercepts the pending write when the session is lost mid-unit (F29)', function () {
    $transactions = new TransferImportTransaction(app(TransferGuardRegistry::class));
    $session = new TransferImportSession(app(TransferGuardRegistry::class));

    $session->run('tax', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', true, function () use ($transactions): void {
        $transactions->run(function (): void {
            // The replacement session is an empty :memory: database — any
            // statement that reached it would fail with a missing-table
            // QueryException, NOT lock_session_lost. The exception type
            // therefore proves the ownership hook intercepted BEFORE the
            // statement executed (F29: no importer write on a replacement).
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
            // object (and its guards) — the probe must catch exactly this.
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
