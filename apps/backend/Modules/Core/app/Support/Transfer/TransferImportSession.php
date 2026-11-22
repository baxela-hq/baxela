<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\TransferLock\TransferLockSchema;
use PDO;
use Throwable;

/**
 * The lock + context lifecycle every import run lives inside (C7,
 * postgres-migration §3.1): the destination-module lease row
 * (`transfer:import:{module}`, independent of the source installation)
 * serializes all imports into a module's shared natural-key namespace;
 * the activated {@see TransferRunContext} installs the ownership hook and
 * gives write units and importers their fencing generation and remap
 * namespace.
 *
 * The lease protocol (driver-agnostic — MySQL named locks are gone):
 *
 * - Acquire: INSERT a fresh row inside a nested transaction (a top-level
 *   call is a plain transaction; inside a caller transaction Laravel
 *   lowers it to a SAVEPOINT), so a concurrent first-acquire's unique
 *   violation rolls back only the savepoint and the outer transaction
 *   stays usable.
 * - Reclaim (the ONLY path that advances `generation`): a conditional
 *   UPDATE gated on `expires_at < clock` — a dead run's lease is
 *   reclaimable after expiry, a live one's is not. The loser of an
 *   acquire race retries inside the configured wait budget, then fails
 *   with `import_in_progress` (F26).
 * - Renew: between write units, token+generation+live-lease conditional
 *   (an expired owner can never re-own — I1).
 * - Release: teardown-first (F30), token+generation-guarded expiry —
 *   best-effort, never masking the run's outcome.
 *
 * All expiry decisions use {@see TransferClock} (the database clock).
 *
 * Teardown is unconditional (F30): the context is deactivated BEFORE the
 * lease release, so no recovery work afterwards can run under (or be
 * blocked by) the import guard. `ModuleTransferRunner::run()` and
 * self-contained importers (Catalog) both enter through here.
 */
final class TransferImportSession
{
    private const string LOCK_PREFIX = 'transfer:import:';

    private const int RETRY_SLEEP_MICROSECONDS = 250_000;

    public function __construct(private TransferGuardRegistry $registry) {}

    /**
     * The destination-module lock name for a module. Fails fast rather
     * than truncating: a truncated name would silently merge two modules'
     * lock scopes.
     */
    public function lockNameFor(string $module): string
    {
        $lockName = self::LOCK_PREFIX.$module;

        if (strlen($lockName) > 64) {
            // Historical MySQL named-lock cap, kept as a naming discipline.
            throw new ImportFailedException(meta: ['reason' => 'lock_name_too_long']);
        }

        return $lockName;
    }

    /**
     * Acquire the destination-module lease, activate the run context, hand
     * it to `$work`, and tear down on every exit path.
     *
     * @template T
     *
     * @param  callable(TransferRunContext): T  $work
     * @return T
     *
     * @throws ImportFailedException with reason `import_in_progress` when
     *                               the lease is held past the configured wait
     * @throws TransferLockSessionLostException
     *                                          when the lease is lost mid-run
     *                                          (fatal — the runner aborts and audits)
     */
    public function run(string $module, string $sourceInstallation, bool $identified, callable $work): mixed
    {
        $connection = DB::connection();
        $this->assertSupportedConnection($connection);

        $lockName = $this->lockNameFor($module);

        [$ownerToken, $generation] = $this->acquire($connection, $lockName);

        $context = new TransferRunContext(
            connection: $connection,
            lockName: $lockName,
            ownerToken: $ownerToken,
            generation: $generation,
            sourceInstallation: $sourceInstallation,
            identified: $identified,
        );

        $this->registry->installGuardOnce($connection);
        $this->registry->activate($connection, $context);

        try {
            return $work($context);
        } finally {
            // Teardown-first (F30): the ownership hook reads an inactive
            // registry before anything else (audit recovery, lease release)
            // gets a chance to run.
            $this->registry->deactivate($connection);
            $this->releaseLeaseBestEffort($context);
        }
    }

    /**
     * @return array{0: string, 1: int} the owner token and fencing generation
     */
    private function acquire(Connection $connection, string $lockName): array
    {
        $waitSeconds = max(0, (int) config('core.transfer.lock_timeout', 10));
        $leaseSeconds = max(1, (int) config('core.transfer.lock_lease_ttl', 60));
        $ownerToken = (string) Str::uuid();
        $deadline = now()->addSeconds($waitSeconds);

        do {
            if ($this->tryInsert($connection, $lockName, $ownerToken, $leaseSeconds)) {
                return [$ownerToken, 1]; // First acquisition is generation 1.
            }

            $generation = $this->tryReclaim($connection, $lockName, $ownerToken, $leaseSeconds);

            if ($generation !== null) {
                return [$ownerToken, $generation];
            }

            // Live contention (F26: same destination module serializes,
            // whatever the source) — retry inside the wait budget.
            usleep(self::RETRY_SLEEP_MICROSECONDS);
        } while (now()->isBefore($deadline));

        throw new ImportFailedException(meta: ['reason' => 'import_in_progress']);
    }

    /**
     * First acquisition: INSERT inside a nested transaction — top-level
     * this is a plain transaction, inside a caller's transaction Laravel
     * lowers it to a SAVEPOINT, so a concurrent first-acquire's unique
     * violation aborts only that savepoint and the caller's transaction
     * remains usable (PG's aborted-transaction trap handled by rollback,
     * never by PHP catching alone).
     */
    private function tryInsert(Connection $connection, string $lockName, string $ownerToken, int $leaseSeconds): bool
    {
        try {
            $connection->transaction(function () use ($connection, $lockName, $ownerToken, $leaseSeconds): void {
                $connection->table(TransferLockSchema::TABLE)->insert([
                    TransferLockSchema::NAME => $lockName,
                    TransferLockSchema::OWNER_TOKEN => $ownerToken,
                    TransferLockSchema::GENERATION => 1,
                    TransferLockSchema::HEARTBEAT_AT => $connection->raw(TransferClock::now($connection)),
                    TransferLockSchema::EXPIRES_AT => $connection->raw(TransferClock::nowPlus($connection, $leaseSeconds)),
                ]);
            });

            return true;
        } catch (QueryException) {
            return false; // Lost the first-acquire race — try reclamation.
        }
    }

    /**
     * Reclaim an expired lease (the only generation advance — I3): the
     * conditional UPDATE is evaluated on the database clock; a live lease
     * blocks reclamation, an expired one transfers ownership atomically.
     */
    private function tryReclaim(Connection $connection, string $lockName, string $ownerToken, int $leaseSeconds): ?int
    {
        $reclaimed = $connection->table(TransferLockSchema::TABLE)
            ->where(TransferLockSchema::NAME, $lockName)
            ->where(TransferLockSchema::EXPIRES_AT, '<', $connection->raw(TransferClock::now($connection)))
            ->update([
                TransferLockSchema::OWNER_TOKEN => $ownerToken,
                TransferLockSchema::GENERATION => $connection->raw(TransferLockSchema::GENERATION.' + 1'),
                TransferLockSchema::HEARTBEAT_AT => $connection->raw(TransferClock::now($connection)),
                TransferLockSchema::EXPIRES_AT => $connection->raw(TransferClock::nowPlus($connection, $leaseSeconds)),
            ]);

        if ($reclaimed !== 1) {
            return null;
        }

        return (int) $connection->table(TransferLockSchema::TABLE)
            ->where(TransferLockSchema::NAME, $lockName)
            ->where(TransferLockSchema::OWNER_TOKEN, $ownerToken)
            ->value(TransferLockSchema::GENERATION);
    }

    /**
     * Import requires a dedicated lock-owning session: persistent
     * connections can transparently swap sessions underneath the captured
     * one, which the unit-boundary session check exists to detect.
     * (Verified: the MySQL connection config carries no
     * PDO::ATTR_PERSISTENT — this guard keeps it that way.)
     */
    private function assertSupportedConnection(Connection $connection): void
    {
        $options = $connection->getConfig('options') ?? [];

        if (($options[PDO::ATTR_PERSISTENT] ?? false) === true) {
            throw new ImportFailedException(meta: ['reason' => 'import_unsupported_connection']);
        }
    }

    /**
     * Best-effort release: expire the lease under token+generation guard.
     * A failed release only means the lease dies at its natural expiry —
     * the ownership row check, not the release, is the source of truth
     * (F28's successor semantics).
     */
    private function releaseLeaseBestEffort(TransferRunContext $context): void
    {
        try {
            $context->connection->table(TransferLockSchema::TABLE)
                ->where(TransferLockSchema::NAME, $context->lockName)
                ->where(TransferLockSchema::OWNER_TOKEN, $context->ownerToken)
                ->where(TransferLockSchema::GENERATION, $context->generation)
                ->update([
                    TransferLockSchema::HEARTBEAT_AT => $context->connection->raw(TransferClock::now($context->connection)),
                    TransferLockSchema::EXPIRES_AT => $context->connection->raw(TransferClock::now($context->connection)),
                ]);
        } catch (Throwable) {
            // Never mask the run's own outcome with a release failure.
        }
    }
}
