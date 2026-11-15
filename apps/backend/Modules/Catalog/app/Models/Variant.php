<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\VariantFactory;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;

// use Modules\Catalog\Database\Factories\VariantFactory;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $price
 * @property int $quantity
 * @property string|null $compare_price
 * @property string|null $cost_price
 * @property bool $is_default
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, OptionValue> $optionValues
 * @property-read Collection<int, Image> $images
 * @property-read Product $product
 */
class Variant extends Model
{
    use BelongToProductTrait;

    /** @use HasFactory<VariantFactory> */
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

    /** @return BelongsToMany<OptionValue, $this> */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(OptionValue::class, VariantOptionValueSchema::TABLE,
            VariantOptionValueSchema::VARIANT_ID, VariantOptionValueSchema::OPTION_VALUE_ID);
    }

    /** @return HasMany<Image, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class, ImageSchema::VARIANT_ID);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
