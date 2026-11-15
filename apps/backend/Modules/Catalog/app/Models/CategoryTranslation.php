<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\CategoryTranslationFactory;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class CategoryTranslation extends Model
{
    /** @use HasFactory<CategoryTranslationFactory> */
    use HasFactory;

    use SlugTrait;

    protected $table = CategoryTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        CategoryTranslationSchema::LANGUAGE_ID,
        CategoryTranslationSchema::TITLE,
        CategoryTranslationSchema::SLUG,
        CategoryTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): CategoryTranslationFactory
    {
        return CategoryTranslationFactory::new();
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
