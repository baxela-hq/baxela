<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use PDO;
use Throwable;

/**
 * Per-run state of one import run (C7): the lock-owning database
 * connection, the lock's name, and the owning session's identity. Created
 * by {@see TransferImportSession} right after the lock is acquired and
 * deactivated in its `finally`; the ownership hook consults it before
 * every statement, and write units re-check it before commit.
 *
 * Session identity is proven by the strongest probe the driver offers:
 *
 * - MySQL/MariaDB: `IS_USED_LOCK` — the source of truth (F28). It catches
 *   server-side session death (network loss, `KILL`) even when the PDO
 *   object lives on, and it names whoever re-acquired the lock.
 * - Other drivers (the SQLite test plane): named locks don't exist, so
 *   ownership is proven by PDO instance identity — `disconnect()` and
 * `reconnect()` both replace the instance, which is exactly the
 *   replacement-session signal. A single-process test database is
 *   serialized by construction, so the named-lock tier adds nothing there.
 *
 * In both cases any failure to even answer — dead PDO, missing PDO — is
 * session loss: fail closed.
 *
 * Holds no importer state — the run's counters live with the runner's
 * closure, so a deactivated context leaves nothing to reactivate (F32).
 */
final class TransferRunContext
{
    /** Drivers with server-side named locks (the IS_USED_LOCK truth). */
    private const array NAMED_LOCK_DRIVERS = ['mysql', 'mariadb'];

    public function __construct(
        public readonly Connection $connection,
        public readonly string $lockName,
        /** The lock-owning session's MySQL connection id (0 where unsupported). */
        public readonly int $ownerConnectionId,
        /** The importing file's source-installation namespace for remap recording. */
        public readonly string $sourceInstallation,
        /** Whether the file carried a valid `source_installation` (C2 tier behavior). */
        public readonly bool $identified,
    ) {
        $this->namedLocks = in_array($this->connection->getDriverName(), self::NAMED_LOCK_DRIVERS, true);
        // Capture the PDO instance the lock was acquired on; under the
        // instance-identity probe, any replacement is session loss.
        $this->ownerPdo = $this->connection->getPdo();
    }

    private bool $namedLocks;

    private PDO $ownerPdo;

    /**
     * Verify THIS session still owns the destination-module lock, via a
     * raw PDO read on the captured connection — bypassing the query
     * builder (and thus the ownership hook itself, so the check cannot
     * recurse).
     *
     * @throws TransferLockSessionLostException
     */
    public function assertOwnership(): void
    {
        try {
            $owned = $this->namedLocks
                ? $this->ownsNamedLock()
                : $this->ownsPdoInstance();
        } catch (Throwable $e) {
            // Cannot even ask: dead or missing PDO means the session that
            // owned the lock is gone. Fail closed.
            throw new TransferLockSessionLostException($e);
        }

        if (! $owned) {
            throw new TransferLockSessionLostException;
        }
    }

    /**
     * The MySQL truth: the named lock is still held by the captured
     * session id — `IS_USED_LOCK` returns that id, null when the lock is
     * free (owner died), or another session's id when re-acquired.
     */
    private function ownsNamedLock(): bool
    {
        $statement = $this->connection->getPdo()->prepare('SELECT IS_USED_LOCK(?)');
        $statement->execute([$this->lockName]);
        $owner = $statement->fetchColumn();

        return $owner !== false && $owner !== null && (int) $owner === $this->ownerConnectionId;
    }

    /**
     * The identity fallback: the connection must still carry the exact
     * PDO instance the lock/context was born on. `disconnect()` nulls it,
     * `reconnect()` replaces it — either way the session changed.
     */
    private function ownsPdoInstance(): bool
    {
        return $this->connection->getPdo() === $this->ownerPdo;
    }
}
