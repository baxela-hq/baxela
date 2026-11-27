<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Catalog\Database\Factories\ProductFactory;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Image\ImageSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Product\ProductTypeEnum;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property ProductTypeEnum $type
 * @property ProductStatusEnum $status
 * @property bool $is_published
 * @property int|null $tax_class_id
 * @property string|null $rating_avg
 * @property int $rating_count
 * @property int|null $brand_id
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, ProductTranslation> $translations
 * @property-read Collection<int, Image> $images
 * @property-read Collection<int, Variant> $variants
 * @property-read Collection<int, ProductAttributeValue> $attributeValues
 * @property-read Collection<int, ProductComment> $comments
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SlugTrait;
    use SoftDeletes;
    use TenantScopedTrait;

    protected $table = ProductSchema::TABLE;

    protected $fillable = [
        ProductSchema::TYPE,
        ProductSchema::STATUS,
        ProductSchema::IS_PUBLISHED,
        ProductSchema::BRAND_ID,
        ProductSchema::TAX_CLASS_ID,
        ProductSchema::RATING_AVG,
        ProductSchema::RATING_COUNT,
    ];

    protected function casts(): array
    {
        return [
            ProductSchema::TYPE => ProductTypeEnum::class,
            ProductSchema::STATUS => ProductStatusEnum::class,
            ProductSchema::IS_PUBLISHED => 'boolean',
            ProductSchema::RATING_AVG => 'decimal:2',
            ProductSchema::RATING_COUNT => 'integer',
        ];
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, CategoryProductSchema::TABLE,
            CategoryProductSchema::PRODUCT_ID, CategoryProductSchema::CATEGORY_ID);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return MorphOne<FeaturedItem, $this> */
    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }

    /** @return HasMany<Option, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class);
    }

    /** @return BelongsToMany<OptionValue, $this> */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(OptionValue::class, VariantOptionValueSchema::TABLE,
            VariantOptionValueSchema::VARIANT_ID, VariantOptionValueSchema::OPTION_VALUE_ID);
    }

    /** @return HasMany<Variant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /** @return HasMany<Image, $this> */
    public function images(): HasMany
    {
        // Variant photos live in the same table carrying a variant_id; the
        // product gallery holds only the shared, variant-less rows.
        return $this->hasMany(Image::class)->whereNull(ImageSchema::VARIANT_ID);
    }

    /** @return HasMany<ProductAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** @return HasMany<ProductComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class);
    }

    /** @return HasMany<ProductTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /** @return HasOne<ProductShipping, $this> */
    public function shipping(): HasOne
    {
        return $this->hasOne(ProductShipping::class);
    }

    /** @return HasMany<ProductSeoTranslation, $this> */
    public function seo(): HasMany
    {
        return $this->hasMany(ProductSeoTranslation::class);
    }
}
