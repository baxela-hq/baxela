<?php

namespace Modules\Order\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Traits\TenantScopedTrait;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;

/**
 * One row per (type, year): the atomic counter behind gapless document
 * numbering (D3). Rows are created under a lock on first allocation for
 * the year and incremented inside the issuing transaction.
 *
 * @mixin Builder<DocumentSequence>
 */
/**
 * @property int $id
 * @property string $type
 * @property int $year
 * @property int $last_number
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @mixin Builder<DocumentSequence>
 */
class DocumentSequence extends Model
{
    use TenantScopedTrait;

    protected $table = DocumentSequenceSchema::TABLE;

    protected $fillable = [
        DocumentSequenceSchema::TYPE,
        DocumentSequenceSchema::YEAR,
        DocumentSequenceSchema::LAST_NUMBER,
    ];

    protected function casts(): array
    {
        return [
            DocumentSequenceSchema::TYPE => DocumentSequenceTypeEnum::class,
        ];
    }
}
