<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Order\Schemas\Document\DocumentSequenceSchema;
use Modules\Order\Schemas\Document\DocumentSequenceTypeEnum;

/**
 * One row per (type, year): the atomic counter behind gapless document
 * numbering (D3). Rows are created under a lock on first allocation for
 * the year and incremented inside the issuing transaction.
 *
 * @mixin Builder
 */
class DocumentSequence extends Model
{
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
