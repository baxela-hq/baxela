<?php

namespace Modules\Core\Exceptions\Transfer;

use Modules\Core\Support\Transfer\TransferGuardRegistry;
use RuntimeException;
use Throwable;

/**
 * The destination-module lock's owning database session is gone (C7/F28):
 * the session that acquired `GET_LOCK` died or the connection object now
 * talks to a replacement session. Fatal for the import run — committed
 * units stay committed, nothing further writes, and the run retries from
 * the beginning once a session is available again (idempotent by remap
 * and natural key).
 */
class TransferLockSessionLostException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('lock_session_lost', previous: $previous);
    }

    /**
     * The per-row isolation catch that keeps one bad row from sinking a
     * section must never swallow this exception: a lost lock session is
     * fatal for the whole run (C7/F28), not a row failure. Importers call
     * this first inside their per-row catches.
     *
     * The exception TYPE alone is not trustworthy evidence: actions rewrap
     * any failure (e.g. Tax's CreationFailedException) without a previous
     * chain, so a session lost mid-row can surface under an arbitrary
     * class. The ownership ledger is the truth — if the run's lock-owning
     * session is gone, the row failure is the run's death.
     */
    public static function rethrow(Throwable $e): void
    {
        if ($e instanceof self) {
            throw $e;
        }

        $context = app(TransferGuardRegistry::class)->activeContext();

        if ($context !== null) {
            try {
                $context->assertOwnership();
            } catch (Throwable) {
                throw new self($e);
            }
        }
    }
}
