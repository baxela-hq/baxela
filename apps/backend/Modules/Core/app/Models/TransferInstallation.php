<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Schemas\TransferInstallation\TransferInstallationSchema;
use Modules\Core\Support\Transfer\InstallationIdentity;

/**
 * The single-row holder of this installation's outbound transfer identity
 * (C1): the uuid stamped into every exported file's `source_installation`.
 * Generated lazily on first export, never rotated automatically — the
 * `transfer:reset-identity` command is the only rotation path, and it
 * deliberately leaves inbound remap history alone.
 *
 * @property int $id
 * @property string $uuid
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class TransferInstallation extends Model
{
    protected $table = TransferInstallationSchema::TABLE;

    protected $fillable = [
        // The fixed singleton id is part of the identity contract: the
        // insert MUST carry it explicitly. Relying on the sequence
        // worked only while it sat at 1 — PostgreSQL sequences do not
        // roll back with transactions, so a prior rolled-back insert
        // would land the row on id 2+ and every later find(1) would
        // miss it, regenerating a new identity per call.
        TransferInstallationSchema::ID,
        TransferInstallationSchema::UUID,
    ];

    /**
     * The current outbound identity, generating the row on first use.
     * Reads go through the store so generation stays concurrency-safe.
     */
    public static function currentUuid(): string
    {
        return app(InstallationIdentity::class)->current();
    }
}
