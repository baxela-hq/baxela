<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use Modules\Core\Schemas\TransferLock\TransferLockSchema;
use PDO;
use Throwable;

/**
 * Per-run state of one import run (C7, postgres-migration §3.1): the
 * lock-owning database connection, the lock's name, this run's owner
 * token, and the fencing generation captured at acquisition. Created by
 * {@see TransferImportSession} right after the lease is acquired and
 * deactivated in its `finally`; the ownership hook consults it before
 * every statement, and write units fence on the lock row itself.
 *
 * Ownership is ROW state, not session state: the run owns the import iff
 * `transfer_locks` still carries its token + generation with a live
 * (unexpired, DB-clock) lease. A replaced session between units is
 * therefore harmless — but a PDO swap MID-UNIT means the server already
 * rolled the unit's transaction back, so the unit-boundary check
 * (`assertUnitSession`) treats it as session loss: fail closed.
 *
 * In both cases any failure to even answer — dead PDO, missing PDO — is
 * session loss: fail closed.
 *
 * Holds no importer state — the run's counters live with the runner's
 * closure, so a deactivated context leaves nothing to reactivate (F32).
 */
final class TransferRunContext
{
    private ?PDO $unitPdo = null;

    public function __construct(
        public readonly Connection $connection,
        public readonly string $lockName,
        /** This run's lease token (uuid) — the ownership identity. */
        public readonly string $ownerToken,
        /** Fencing generation captured at acquisition (reclamation advances it). */
        public readonly int $generation,
        /** The importing file's source-installation namespace for remap recording. */
        public readonly string $sourceInstallation,
        /** Whether the file carried a valid `source_installation` (C2 tier behavior). */
        public readonly bool $identified,
    ) {}

    /**
     * Verify THIS run still owns the destination-module lock, via a raw
     * PDO read on the captured connection — bypassing the query builder
     * (and thus the ownership hook itself, so the check cannot recurse).
     *
     * @throws TransferLockSessionLostException
     */
    public function assertOwnership(): void
    {
        // Mid-unit, ownership additionally requires the unit's own PDO:
        // a swapped instance proves the server dropped the transaction.
        if ($this->unitPdo !== null) {
            try {
                $samePdo = $this->connection->getPdo() === $this->unitPdo;
            } catch (Throwable $e) {
                throw new TransferLockSessionLostException($e);
            }

            if (! $samePdo) {
                throw new TransferLockSessionLostException;
            }
        }

        try {
            $owned = $this->ownsLeaseRow();
        } catch (Throwable $e) {
            // Cannot even ask: dead or missing PDO means the run cannot
            // prove ownership. Fail closed.
            throw new TransferLockSessionLostException($e);
        }

        if (! $owned) {
            throw new TransferLockSessionLostException;
        }
    }

    /**
     * The row probe (F28 successor): the lock row must still carry this
     * run's token AND generation with an unexpired lease — evaluated on
     * the database clock, never PHP time (§3.1.0).
     *
     * @throws Throwable on any connection/PDO failure
     */
    private function ownsLeaseRow(): bool
    {
        // Between units the lease row — not the session — is the authority,
        // so a dropped connection is re-established for the probe (raw
        // getPdo() alone returns null after disconnect; the lazy reconnect
        // normally lives inside query execution). Mid-unit swaps are
        // already rejected above before this point.
        $this->connection->reconnectIfMissingConnection();

        $sql = sprintf(
            'SELECT %1$s, %2$s, CASE WHEN %3$s > %4$s THEN 1 ELSE 0 END AS lease_live FROM %5$s WHERE %6$s = ?',
            TransferLockSchema::OWNER_TOKEN,
            TransferLockSchema::GENERATION,
            TransferLockSchema::EXPIRES_AT,
            TransferClock::now($this->connection),
            TransferLockSchema::TABLE,
            TransferLockSchema::NAME,
        );

        $statement = $this->connection->getPdo()->prepare($sql);
        $statement->execute([$this->lockName]);
        $row = $statement->fetchObject();

        return $row !== false
            && (string) $row->{TransferLockSchema::OWNER_TOKEN} === $this->ownerToken
            && (int) $row->{TransferLockSchema::GENERATION} === $this->generation
            && (int) $row->lease_live === 1;
    }

    /**
     * The §3.1.1 fenced write-unit boundary: lock the row FOR UPDATE and
     * verify token + generation + live lease INSIDE the unit's open
     * transaction, before any protected write. The row lock is held to
     * COMMIT, so no reclamation can interleave between this verification
     * and the unit's writes — there is no time-of-check/time-of-use gap.
     *
     * Runs through the connection (the ownership hook fires and passes
     * while the run is healthy).
     *
     * @throws TransferLockSessionLostException when the run no longer owns
     *                                          a live lease on the row
     */
    public function fenceRow(): void
    {
        // SQLite (test plane) has no FOR UPDATE — single-writer database
        // locking gives the serialization; the row check still applies.
        $forUpdate = in_array($this->connection->getDriverName(), ['pgsql', 'mysql', 'mariadb'], true)
            ? ' FOR UPDATE'
            : '';

        $sql = sprintf(
            'SELECT %1$s, %2$s, CASE WHEN %3$s > %4$s THEN 1 ELSE 0 END AS lease_live FROM %5$s WHERE %6$s = ?%7$s',
            TransferLockSchema::OWNER_TOKEN,
            TransferLockSchema::GENERATION,
            TransferLockSchema::EXPIRES_AT,
            TransferClock::now($this->connection),
            TransferLockSchema::TABLE,
            TransferLockSchema::NAME,
            $forUpdate,
        );

        $row = $this->connection->selectOne($sql, [$this->lockName]);

        if ($row === null
            || (string) $row->{TransferLockSchema::OWNER_TOKEN} !== $this->ownerToken
            || (int) $row->{TransferLockSchema::GENERATION} !== $this->generation
            || (int) $row->lease_live !== 1) {
            throw new TransferLockSessionLostException;
        }
    }

    /**
     * Pre-commit unit check: the transaction this unit opened must still
     * be the one on the captured PDO. A swap means the server already
     * rolled it back — committing on the replacement would be a lie.
     */
    public function assertUnitSession(): void
    {
        if ($this->unitPdo === null || $this->connection->getPdo() !== $this->unitPdo) {
            throw new TransferLockSessionLostException;
        }
    }

    /**
     * @internal called by {@see TransferImportTransaction} around each unit
     */
    public function beginUnit(): void
    {
        $this->unitPdo = $this->connection->getPdo();
    }

    /**
     * @internal called by {@see TransferImportTransaction} on every exit path
     */
    public function endUnit(): void
    {
        $this->unitPdo = null;
    }

    /**
     * Between-units lease renewal (§3.1.2 step 3): conditional on token +
     * generation AND a still-live lease — an expired owner can never renew
     * itself back into ownership (invariant I1). Zero affected rows means
     * the lease was lost: fail closed.
     *
     * @throws TransferLockSessionLostException
     */
    public function renewLease(): void
    {
        $connection = $this->connection;
        $leaseSeconds = max(1, (int) config('core.transfer.lock_lease_ttl', 60));

        $renewed = $connection->table(TransferLockSchema::TABLE)
            ->where(TransferLockSchema::NAME, $this->lockName)
            ->where(TransferLockSchema::OWNER_TOKEN, $this->ownerToken)
            ->where(TransferLockSchema::GENERATION, $this->generation)
            ->where(TransferLockSchema::EXPIRES_AT, '>', $connection->raw(TransferClock::now($connection)))
            ->update([
                TransferLockSchema::HEARTBEAT_AT => $connection->raw(TransferClock::now($connection)),
                TransferLockSchema::EXPIRES_AT => $connection->raw(TransferClock::nowPlus($connection, $leaseSeconds)),
            ]);

        if ($renewed === 0) {
            throw new TransferLockSessionLostException;
        }
    }
}
