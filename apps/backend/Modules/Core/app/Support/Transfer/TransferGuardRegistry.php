<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * The stable registry the ownership guard consults (F32): maps each
 * connection object to at most one active {@see TransferRunContext}, and
 * installs the connection's ownership hook exactly once per connection
 * object — never once per run, so repeated imports on a long-lived worker
 * connection accumulate nothing.
 *
 * The hook closure captures only this registry and the connection; run
 * state lives exclusively in the context, which is deactivated during
 * teardown — a retained hook reads an empty map and no-ops (F31).
 */
final class TransferGuardRegistry
{
    /** @var WeakMap<Connection, TransferRunContext> */
    private WeakMap $contexts;

    /** @var WeakMap<Connection, true> connections whose guard is installed */
    private WeakMap $installed;

    public function __construct()
    {
        $this->contexts = new WeakMap;
        $this->installed = new WeakMap;
    }

    /**
     * Install the connection's ownership hook once (F32): before every
     * statement — and crucially before Laravel's lazy
     * `reconnectIfMissingConnection()` can swap the session underneath it
     * (Connection.php:808-812) — the hook re-verifies that the statement
     * is about to run on the session that owns the run's lock. No importer
     * write can ever execute on a replacement session (F29).
     */
    public function installGuardOnce(Connection $connection): void
    {
        if (isset($this->installed[$connection])) {
            return;
        }

        $this->installed[$connection] = true;

        $registry = $this;
        $connection->beforeExecuting(function () use ($registry, $connection): void {
            $context = $registry->contextFor($connection);
            if ($context === null) {
                return; // Inert outside an import run (F31): one map lookup.
            }

            $context->assertOwnership();
        });
    }

    public function activate(Connection $connection, TransferRunContext $context): void
    {
        $this->contexts[$connection] = $context;
    }

    /**
     * End the run: the hook's next invocation reads no context and no-ops.
     * Guaranteed by the session's `finally` on every exit path.
     */
    public function deactivate(Connection $connection): void
    {
        unset($this->contexts[$connection]);
    }

    public function contextFor(Connection $connection): ?TransferRunContext
    {
        return $this->contexts[$connection] ?? null;
    }

    /**
     * The active context for the default connection — the seam importers
     * use to reach their run's namespace for remap recording. The write
     * helper refuses to run without one, so no importer write can bypass
     * the run lifecycle.
     */
    public function activeContext(): ?TransferRunContext
    {
        return $this->contextFor(DB::connection());
    }
}
