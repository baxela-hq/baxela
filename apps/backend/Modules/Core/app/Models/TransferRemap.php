<?php

namespace Modules\Core\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Core\Schemas\TransferRemap\TransferRemapSchema;

/**
 * One persisted cross-import mapping: "source installation X's row with
 * id N (module, entity) became local id M here" (C1). Written inside the
 * import unit's transaction alongside the entity write, so a mapping and
 * the row it describes are born and die together (C7/F11). Lookup is the
 * FIRST tier of reference resolution; natural keys and raw ids follow.
 *
 * @property int $id
 * @property string $source_installation
 * @property string $module
 * @property string $entity
 * @property int $source_id
 * @property int $local_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class TransferRemap extends Model
{
    use TenantScopedTrait;

    protected $table = TransferRemapSchema::TABLE;

    protected $fillable = [
        TransferRemapSchema::SOURCE_INSTALLATION,
        TransferRemapSchema::MODULE,
        TransferRemapSchema::ENTITY,
        TransferRemapSchema::SOURCE_ID,
        TransferRemapSchema::LOCAL_ID,
    ];
}
