<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Database\Factories\CategoryTranslationFactory;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class CategoryTranslation extends Model
{
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
