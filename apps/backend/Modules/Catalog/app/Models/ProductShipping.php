<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Product\DimensionUnitEnum;
use Modules\Catalog\Schemas\Product\ProductShippingSchema;
use Modules\Catalog\Schemas\Product\WeightUnitEnum;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $product_id
 * @property string|null $weight
 * @property WeightUnitEnum|null $weight_unit
 * @property string|null $package_length
 * @property string|null $package_width
 * @property string|null $package_height
 * @property DimensionUnitEnum|null $dimension_unit
 * @property bool $requires_shipping
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ProductShipping extends Model
{
    use TenantScopedTrait;

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
