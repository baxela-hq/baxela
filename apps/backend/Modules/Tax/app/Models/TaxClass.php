<?php

namespace Modules\Tax\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tax\Database\Factories\TaxClassFactory;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;

/**
 * @mixin Builder
 */
class TaxClass extends Model
{
    use HasFactory;

    protected $table = TaxClassSchema::TABLE;

    protected $fillable = [
        TaxClassSchema::NAME,
        TaxClassSchema::IS_DEFAULT,
        TaxClassSchema::IS_SHIPPING,
    ];

    protected function casts(): array
    {
        return [
            TaxClassSchema::IS_DEFAULT => 'boolean',
            TaxClassSchema::IS_SHIPPING => 'boolean',
        ];
    }

    protected static function newFactory(): TaxClassFactory
    {
        return TaxClassFactory::new();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, TaxRateSchema::TAX_CLASS_ID);
    }
}
