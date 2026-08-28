<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Catalog\Database\Factories\VariantFactory;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

// use Modules\Catalog\Database\Factories\VariantFactory;

class Variant extends Model
{
    use BelongToProductTrait;
    use HasFactory;

    protected $table = VariantSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        VariantSchema::SKU,
        VariantSchema::BARCODE,
        VariantSchema::PRICE,
        VariantSchema::QUANTITY,
        VariantSchema::COMPARE_PRICE,
        VariantSchema::COST_PRICE,
        VariantSchema::IS_DEFAULT,
    ];

    protected function casts(): array
    {
        return [
            VariantSchema::IS_DEFAULT => 'boolean',
        ];
    }

    protected static function newFactory(): VariantFactory
    {
        return VariantFactory::new();
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(OptionValue::class, VariantOptionValueSchema::TABLE,
            VariantOptionValueSchema::VARIANT_ID, VariantOptionValueSchema::OPTION_VALUE_ID);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
