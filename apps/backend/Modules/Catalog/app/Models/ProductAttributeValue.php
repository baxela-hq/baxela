<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $product_id
 * @property int $attribute_id
 * @property int|null $attribute_value_id
 * @property string|null $text_value
 * @property string|null $number_value
 * @property bool|null $boolean_value
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ProductAttributeValue extends Model
{
    use TenantScopedTrait;

    public $timestamps = false;

    protected $table = ProductAttributeValueSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        ProductAttributeValueSchema::PRODUCT_ID,
        ProductAttributeValueSchema::ATTRIBUTE_ID,
        ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID,
        ProductAttributeValueSchema::TEXT_VALUE,
        ProductAttributeValueSchema::NUMBER_VALUE,
        ProductAttributeValueSchema::BOOLEAN_VALUE,
    ];

    protected function casts(): array
    {
        return [
            ProductAttributeValueSchema::BOOLEAN_VALUE => 'boolean',
            ProductAttributeValueSchema::NUMBER_VALUE => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, ProductAttributeValueSchema::ATTRIBUTE_ID);
    }

    /** @return BelongsTo<AttributeValue, $this> */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID);
    }
}
