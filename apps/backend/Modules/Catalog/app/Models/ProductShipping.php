<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Product\DimensionUnitEnum;
use Modules\Catalog\Schemas\Product\ProductShippingSchema;
use Modules\Catalog\Schemas\Product\WeightUnitEnum;

/**
 * @property int $id
 */
class ProductShipping extends Model
{
    protected $table = ProductShippingSchema::TABLE;

    protected $fillable = [
        ProductShippingSchema::PRODUCT_ID,
        ProductShippingSchema::WEIGHT,
        ProductShippingSchema::WEIGHT_UNIT,
        ProductShippingSchema::PACKAGE_LENGTH,
        ProductShippingSchema::PACKAGE_WIDTH,
        ProductShippingSchema::PACKAGE_HEIGHT,
        ProductShippingSchema::DIMENSION_UNIT,
        ProductShippingSchema::REQUIRES_SHIPPING,
    ];

    protected function casts(): array
    {
        return [
            ProductShippingSchema::WEIGHT_UNIT => WeightUnitEnum::class,
            ProductShippingSchema::DIMENSION_UNIT => DimensionUnitEnum::class,
            ProductShippingSchema::REQUIRES_SHIPPING => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, ProductShippingSchema::PRODUCT_ID);
    }
}
