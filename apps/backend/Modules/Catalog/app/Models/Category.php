<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Catalog\Database\Factories\CategoryFactory;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Core\Models\Traits\SlugTrait;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $position
 * @property int|null $image_media_id
 * @property string|null $image_url
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use SlugTrait;

    protected $table = CategorySchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        CategorySchema::PARENT_ID,
        CategorySchema::POSITION,
        CategorySchema::IMAGE_MEDIA_ID,
        CategorySchema::IMAGE_URL,
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [];
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, CategoryProductSchema::TABLE,
            CategoryProductSchema::CATEGORY_ID, CategoryProductSchema::PRODUCT_ID);
    }

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, CategoryAttributeSchema::TABLE,
            CategoryAttributeSchema::CATEGORY_ID, CategoryAttributeSchema::ATTRIBUTE_ID)
            ->withPivot(CategoryAttributeSchema::POSITION)
            ->orderByPivot(CategoryAttributeSchema::POSITION);
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /** @return MorphOne<FeaturedItem, $this> */
    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }
}
