<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\Product\ProductAttributeValueSchema;

class ProductAttributeValue extends Model
{
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, ProductAttributeValueSchema::ATTRIBUTE_ID);
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, ProductAttributeValueSchema::ATTRIBUTE_VALUE_ID);
    }
}
