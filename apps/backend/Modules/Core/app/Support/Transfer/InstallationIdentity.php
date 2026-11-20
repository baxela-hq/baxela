<?php

namespace Modules\Core\Support\Transfer;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Modules\Core\Models\TransferInstallation;
use Modules\Core\Schemas\TransferInstallation\TransferInstallationSchema;

/**
 * This installation's OUTBOUND transfer identity (C1): the uuid stamped
 * into exported files' `source_installation` key, naming this store as the
 * source when one of its files is imported elsewhere.
 *
 * Lifecycle: generated lazily on first export (single-row table, fixed
 * primary key — a concurrent first write loses the insert race and adopts
 * the winner), preserved by ordinary deploys and database restores, and
 * rotated ONLY by the `transfer:reset-identity` command. Rotation is the
 * mandatory clone-init step (snapshots, staging-from-prod, pre-populated
 * images) because a clone must not keep exporting under its origin's name.
 *
 * Deliberately NOT tied to inbound remap history: `transfer_remaps` rows
 * are keyed by the SOURCE installation of each import, so rotating this
 * uuid — or cloning the database — leaves valid inbound mappings intact.
 */
final class InstallationIdentity
{
    /**
     * The current outbound uuid, generating the identity row on first
     * use. Concurrency-safe: the fixed-id insert is the serialization
     * point, and the unique-violation loser re-reads the winner.
     */
    public function current(): string
    {
        $existing = TransferInstallation::query()
            ->find(TransferInstallationSchema::FIXED_ID);
        if ($existing !== null) {
            return (string) $existing->{TransferInstallationSchema::UUID};
        }

        $uuid = (string) Str::uuid();

        try {
            TransferInstallation::query()->create([
                TransferInstallationSchema::ID => TransferInstallationSchema::FIXED_ID,
                TransferInstallationSchema::UUID => $uuid,
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent boot generated the identity first — adopt it.
            return (string) TransferInstallation::query()
                ->findOrFail(TransferInstallationSchema::FIXED_ID)
                ->{TransferInstallationSchema::UUID};
        }

        return $uuid;
    }

    /**
     * Rotate the outbound identity (clone init / explicit reset): the
     * single row's uuid is replaced, inbound remap history is untouched.
     */
    public function rotate(): string
    {
        $uuid = (string) Str::uuid();

        TransferInstallation::query()->updateOrCreate(
            [TransferInstallationSchema::ID => TransferInstallationSchema::FIXED_ID],
            [TransferInstallationSchema::UUID => $uuid],
        );

        return $uuid;
    }
}
