<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tax\Database\Factories\TaxRateFactory;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

/**
 * @mixin Builder
 */
class TaxRate extends Model
{
    use HasFactory;

    protected $table = TaxRateSchema::TABLE;

    protected $fillable = [
        TaxRateSchema::NAME,
        TaxRateSchema::COUNTRY_CODE,
        TaxRateSchema::TAX_CLASS_ID,
        TaxRateSchema::RATE,
        TaxRateSchema::IS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            // decimal string ('8.5000') — parsed to integer rate-units by
            // TaxMath, never through a float
            TaxRateSchema::RATE => 'decimal:4',
            TaxRateSchema::IS_ACTIVE => 'boolean',
        ];
    }

    protected static function newFactory(): TaxRateFactory
    {
        return TaxRateFactory::new();
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class, TaxRateSchema::TAX_CLASS_ID);
    }
}
