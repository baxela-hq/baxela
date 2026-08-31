<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Database\Factories\ProductFactory;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Core\Models\Traits\SlugTrait;

class Product extends Model
{
    use HasFactory;
    use SlugTrait;
    use SoftDeletes;

    protected $table = ProductSchema::TABLE;

    protected $fillable = [
        ProductSchema::TYPE,
        ProductSchema::STATUS,
        ProductSchema::IS_PUBLISHED,
    ];

    protected function casts(): array
    {
        return [
            ProductSchema::TYPE => ProductTypeEnum::class,
            ProductSchema::STATUS => ProductStatusEnum::class,
            ProductSchema::IS_PUBLISHED => 'boolean',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, CategoryProductSchema::TABLE,
            CategoryProductSchema::PRODUCT_ID, CategoryProductSchema::CATEGORY_ID);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(OptionValue::class, VariantOptionValueSchema::TABLE,
            VariantOptionValueSchema::VARIANT_ID, VariantOptionValueSchema::OPTION_VALUE_ID);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function shipping(): HasOne
    {
        return $this->hasOne(ProductShipping::class);
    }

    public function seo(): HasMany
    {
        return $this->hasMany(ProductSeoTranslation::class);
    }
}
