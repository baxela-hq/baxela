<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Exceptions\Transfer\ImportFailedException;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use PDO;
use Throwable;

/**
 * The lock + context lifecycle every import run lives inside (C7): the
 * destination-module named lock (`transfer:import:{module}`, independent
 * of the source installation) serializes all imports into a module's
 * shared natural-key namespace; the activated {@see TransferRunContext}
 * installs the ownership hook and gives write units and importers their
 * lock-owning session, owner id, and remap namespace.
 *
 * Teardown is unconditional (F30): the context is deactivated BEFORE the
 * lock release, so no recovery work afterwards can run under (or be
 * blocked by) the import guard. `ModuleTransferRunner::run()` and
 * self-contained importers (Catalog) both enter through here.
 */
final class TransferImportSession
{
    private const string LOCK_PREFIX = 'transfer:import:';

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
            // MySQL named locks cap at 64 characters.
            throw new ImportFailedException(meta: ['reason' => 'lock_name_too_long']);
        }

        return $lockName;
    }

    /**
     * Acquire the destination-module lock, activate the run context, hand
     * it to `$work`, and tear down on every exit path.
     *
     * @template T
     *
     * @param  callable(TransferRunContext): T  $work
     * @return T
     *
     * @throws ImportFailedException with reason `import_in_progress` when
     *                               the lock is held past the configured wait
     * @throws TransferLockSessionLostException
     *                                          when the lock-owning session dies mid-run
     *                                          (fatal — the runner aborts and audits)
     */
    public function run(string $module, string $sourceInstallation, bool $identified, callable $work): mixed
    {
        $connection = DB::connection();
        $this->assertSupportedConnection($connection);

        $lockName = $this->lockNameFor($module);
        $ownerConnectionId = 0;

        if ($this->supportsNamedLocks($connection)) {
            $timeout = (int) config('core.transfer.lock_timeout', 10);

            $acquired = $connection->selectOne(
                'SELECT GET_LOCK(?, ?) AS acquired',
                [$lockName, $timeout],
            );
            if ($acquired === null || (int) $acquired->acquired !== 1) {
                // Held by a concurrent import past the wait budget (F26: same
                // destination module serializes, whatever the source).
                throw new ImportFailedException(meta: ['reason' => 'import_in_progress']);
            }

            $ownerRow = $connection->selectOne('SELECT IS_USED_LOCK(?) AS owner', [$lockName]);
            $ownerConnectionId = (int) $ownerRow->owner;
        }
        // Drivers without named locks (the SQLite test plane) skip the
        // server-side lock: a single-process test database is serialized
        // by construction, and the run context falls back to proving
        // session identity by PDO instance (see TransferRunContext).

        $context = new TransferRunContext(
            connection: $connection,
            lockName: $lockName,
            ownerConnectionId: $ownerConnectionId,
            sourceInstallation: $sourceInstallation,
            identified: $identified,
        );

        $this->registry->installGuardOnce($connection);
        $this->registry->activate($connection, $context);

        try {
            return $work($context);
        } finally {
            // Teardown-first (F30): the ownership hook reads an inactive
            // registry before anything else (audit recovery, lock release)
            // gets a chance to run.
            $this->registry->deactivate($connection);
            $this->releaseLockBestEffort($context);
        }
    }

    /**
     * Import requires a dedicated lock-owning session: persistent
     * connections can transparently swap sessions underneath the captured
     * one, which the whole session-ownership design exists to prevent.
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
     * Best-effort release: session death already released the lock, and a
     * RELEASE_LOCK on a replacement session returning 0 proves nothing —
     * the ownership check, not the release, is the source of truth (F28).
     */
    private function releaseLockBestEffort(TransferRunContext $context): void
    {
        if (! $this->supportsNamedLocks($context->connection)) {
            return;
        }

        try {
            $context->connection->selectOne(
                'SELECT RELEASE_LOCK(?) AS released',
                [$context->lockName],
            );
        } catch (Throwable) {
            // Never mask the run's own outcome with a release failure.
        }
    }

    /**
     * Whether the driver offers server-side named locks — the only tier
     * where concurrent imports actually contend.
     */
    private function supportsNamedLocks(Connection $connection): bool
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
