<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\CategoryFactory;
use Modules\Catalog\Schemas\Category\CategoryAttributeSchema;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Core\Models\Traits\SlugTrait;

class Category extends Model
{
    use HasFactory;
    use SlugTrait;

    protected $table = CategorySchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        CategorySchema::PARENT_ID,
        CategorySchema::POSITION,
    ];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, CategoryProductSchema::TABLE,
            CategoryProductSchema::CATEGORY_ID, CategoryProductSchema::PRODUCT_ID);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, CategoryAttributeSchema::TABLE,
            CategoryAttributeSchema::CATEGORY_ID, CategoryAttributeSchema::ATTRIBUTE_ID)
            ->withPivot(CategoryAttributeSchema::POSITION)
            ->orderByPivot(CategoryAttributeSchema::POSITION);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }
}
