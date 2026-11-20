<?php

namespace Modules\Core\Support\Transfer;

use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;

/**
 * Shared retrofit for standard importers that record their ids into the
 * persisted remap ledger (C1): one method runs a row's writes and its
 * remap record inside the ownership-verified import transaction (C7/F11),
 * so a mapping and the row it describes are born together and a failed
 * unit leaves neither.
 *
 * Callers invoke `remapUnit()` only on the real-write path — dry runs
 * match and count before any write, so nothing is recorded (C4).
 */
trait RecordsTransferRemaps
{
    private TransferImportTransaction $transferUnits;

    private TransferRemapInterface $transferRemaps;

    private function initTransferRecording(): void
    {
        $this->transferUnits = app(TransferImportTransaction::class);
        $this->transferRemaps = app(TransferRemapInterface::class);
    }

    /**
     * Run one row's writes and record its source-id → local-id mapping
     * atomically. `$writes` returns the created/updated record's local
     * key (or null when the path wrote nothing worth mapping — SKIP).
     *
     * @param  callable(): ?int  $writes
     */
    private function remapUnit(ImportOptions $options, string $module, string $entity, int $sourceId, callable $writes): void
    {
        if ($options->dryRun) {
            return;
        }

        $this->transferUnits->run(function (TransferRunContext $context) use ($module, $entity, $sourceId, $writes): void {
            $localId = $writes();

            if ($localId !== null) {
                $this->transferRemaps->record($context->sourceInstallation, $module, $entity, $sourceId, $localId);
            }
        });
    }
}
