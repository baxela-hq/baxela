<?php

namespace Modules\Core\Support\Transfer;

use Modules\Core\Contracts\Gateways\Transfer\TransferRemapInterface;
use Modules\Core\Models\TransferRemap;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;

/**
 * The transfer_remaps-backed implementation of the remap ledger (C1).
 * Recording runs inside the caller's write-unit transaction (entity write
 * + remap record are atomic — F11); the unique
 * (source_installation, module, entity, source_id) index makes duplicate
 * recording idempotent and serializes concurrent recorders on the same
 * source row.
 */
final class TransferRemapStore implements TransferRemapInterface
{
    public function record(string $sourceInstallation, string $module, string $entity, int $sourceId, int $localId): void
    {
        // Upsert semantics are the convergence point of repeated restores:
        // re-importing the same source row overwrites the mapping, so the
        // newest mapping for a source id always wins.
        TransferRemap::query()->updateOrCreate(
            [
                TransferRemapSchema::SOURCE_INSTALLATION => $sourceInstallation,
                TransferRemapSchema::MODULE => $module,
                TransferRemapSchema::ENTITY => $entity,
                TransferRemapSchema::SOURCE_ID => $sourceId,
            ],
            [
                TransferRemapSchema::LOCAL_ID => $localId,
            ],
        );
    }

    public function resolve(string $sourceInstallation, string $module, string $entity, int $sourceId): ?int
    {
        $remap = TransferRemap::query()
            ->where(TransferRemapSchema::SOURCE_INSTALLATION, $sourceInstallation)
            ->where(TransferRemapSchema::MODULE, $module)
            ->where(TransferRemapSchema::ENTITY, $entity)
            ->where(TransferRemapSchema::SOURCE_ID, $sourceId)
            ->first();

        if ($remap === null) {
            return null;
        }

        return (int) $remap->{TransferRemapSchema::LOCAL_ID};
    }

    public function forgetByLocalIds(string $sourceInstallation, string $module, string $entity, array $localIds): void
    {
        if ($localIds === []) {
            return;
        }

        TransferRemap::query()
            ->where(TransferRemapSchema::SOURCE_INSTALLATION, $sourceInstallation)
            ->where(TransferRemapSchema::MODULE, $module)
            ->where(TransferRemapSchema::ENTITY, $entity)
            ->whereIn(TransferRemapSchema::LOCAL_ID, $localIds)
            ->delete();
    }
}
