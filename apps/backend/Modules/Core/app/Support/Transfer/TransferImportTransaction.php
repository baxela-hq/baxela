<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Support\Facades\Log;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use RuntimeException;
use Throwable;

/**
 * The one and only write-unit boundary an importer may use (C7): an
 * ownership-verified manual transaction around a unit's entity write(s)
 * and their remap record(s), so the mapping and the row it describes are
 * born together (F11) and neither survives a failure alone.
 *
 * Why manual transaction control (`beginTransaction`/`commit`, never
 * `DB::transaction()`): the pre-commit ownership re-check must sit between
 * the callback and the commit, and Laravel's active-transaction guard
 * (`Connection::handleQueryException`, :1018-1022 — `transactions >= 1`
 * rethrows) is what turns a mid-unit connection loss into an abort instead
 * of a transparent reconnect-and-retry on a replacement session. That
 * boundary is load-bearing, not incidental.
 *
 * Invariants (C7): every importer write runs inside this helper; a lost
 * session aborts the unit and the run; business-data writes through raw
 * PDO or outside this boundary are forbidden.
 */
final class TransferImportTransaction
{
    public function __construct(private TransferGuardRegistry $registry) {}

    /**
     * Run one write unit on the lock-owning session.
     *
     * @template T
     *
     * @param  callable(TransferRunContext): T  $unit  receives the active
     *                                                 run context (namespace,
     *                                                 identified flag) for
     *                                                 remap recording
     * @return T
     *
     * @throws TransferLockSessionLostException when the lock-owning session
     *                                          is gone (fatal for the run)
     * @throws RuntimeException with reason `import_context_missing` when no
     *                          run is active — an importer
     *                          writing outside a run
     */
    public function run(callable $unit): mixed
    {
        $context = $this->registry->activeContext();

        if ($context === null) {
            throw new RuntimeException('import_context_missing');
        }

        $context->assertOwnership();

        $context->connection->beginTransaction();

        try {
            $result = $unit($context);

            // Pre-commit re-check (F28): ownership can be lost mid-unit;
            // it must be re-proven before anything commits.
            $context->assertOwnership();

            $context->connection->commit();

            return $result;
        } catch (Throwable $e) {
            // Classify FIRST (F33): is the lock-owning session gone? Only
            // then decide what the rollback means.
            $sessionLost = false;

            try {
                $context->assertOwnership();
            } catch (TransferLockSessionLostException) {
                $sessionLost = true;
            }

            $this->rollbackBestEffort($context, $sessionLost);

            if ($sessionLost) {
                // The unit is over and so is the run: never rethrown as a
                // per-row failure, never continued past.
                throw $e instanceof TransferLockSessionLostException
                    ? $e
                    : new TransferLockSessionLostException($e);
            }

            // Session intact: the failure belongs to this row alone —
            // rethrow the original so the importer records it and the run
            // continues (C5 per-row isolation).
            throw $e;
        }
    }

    /**
     * Best-effort rollback (F33): when the original session died, its
     * uncommitted transaction already ended by session termination, and
     * a rollback against a replacement PDO may fail (no active
     * transaction) or even be impossible. Such a failure is recorded for
     * diagnostics — it never masks the `lock_session_lost` classification.
     */
    private function rollbackBestEffort(TransferRunContext $context, bool $sessionLost): void
    {
        try {
            $context->connection->rollBack();
        } catch (Throwable $e) {
            if ($sessionLost) {
                Log::warning('rollback_after_session_loss_failed', [
                    'lock' => $context->lockName,
                    'reason' => $e->getMessage(),
                ]);
            } else {
                // A rollback failure on a healthy session is real trouble:
                // let it surface next to the original row failure.
                report($e);
            }
        }
    }
}
