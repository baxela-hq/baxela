<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Support\Facades\Log;
use Modules\Core\Exceptions\Transfer\TransferLockSessionLostException;
use RuntimeException;
use Throwable;

/**
 * The one and only write-unit boundary an importer may use (C7,
 * postgres-migration §3.1.1): a fenced, ownership-verified manual
 * transaction around a unit's entity write(s) and their remap record(s),
 * so the mapping and the row it describes are born together (F11) and
 * neither survives a failure alone.
 *
 * The atomic boundary (no time-of-check/time-of-use gap): the unit's
 * transaction first locks the lease row `FOR UPDATE` and verifies token +
 * generation + live lease (DB clock) — then the protected writes run in
 * the SAME transaction, and COMMIT releases the row lock. A competing
 * reclamation UPDATE cannot interleave between verification and writes;
 * a stale owner sees a mismatch and writes nothing.
 *
 * Why manual transaction control (`beginTransaction`/`commit`, never
 * `DB::transaction()`): the fenced verification and the pre-commit
 * session check must sit between the callback and the commit, and
 * Laravel's active-transaction guard (`Connection::handleQueryException`
 * — `transactions >= 1` rethrows) is what turns a mid-unit connection
 * loss into an abort instead of a transparent reconnect-and-retry on a
 * replacement session. That boundary is load-bearing, not incidental.
 *
 * Between units the lease is renewed (token + generation + live-lease
 * conditional — an expired owner can never renew itself back into
 * ownership, invariant I1); a failed renewal is fatal for the run.
 *
 * Invariants (C7): every importer write runs inside this helper; a lost
 * lease aborts the unit and the run; business-data writes through raw
 * PDO or outside this boundary are forbidden.
 */
final class TransferImportTransaction
{
    public function __construct(private TransferGuardRegistry $registry) {}

    /**
     * Run one fenced write unit on the lock-owning session.
     *
     * @template T
     *
     * @param  callable(TransferRunContext): T  $unit  receives the active
     *                                                 run context (namespace,
     *                                                 identified flag) for
     *                                                 remap recording
     * @return T
     *
     * @throws TransferLockSessionLostException when the lease is lost
     *                                          (fatal for the run)
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
        $context->beginUnit();

        try {
            // §3.1.1: lock the lease row and verify ownership INSIDE this
            // transaction, before any protected write. The row lock holds
            // until COMMIT — nothing can reclaim between check and write.
            $context->fenceRow();

            $result = $unit($context);

            // Pre-commit re-check (F28's successor): the unit's own PDO
            // must still carry the transaction — a swapped session means
            // the server already rolled it back, so commit would be a lie.
            // (The lease row itself cannot have changed: this transaction
            // holds its FOR UPDATE lock from fenceRow() until COMMIT.)
            $context->assertUnitSession();

            $context->connection->commit();
        } catch (Throwable $e) {
            // Classify FIRST (F33): is the lease/session gone? Only then
            // decide what the rollback means.
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

            // Lease intact: the failure belongs to this row alone —
            // rethrow the original so the importer records it and the run
            // continues (C5 per-row isolation).
            throw $e;
        } finally {
            $context->endUnit();
        }

        // Between-units renewal — success path only, so a renewal failure
        // can never mask a row-level outcome. A lost renewal is fatal for
        // the run (I1: expired owners never renew themselves back in).
        $context->renewLease();

        return $result;
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
