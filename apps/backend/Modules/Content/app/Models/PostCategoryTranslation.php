<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostCategoryTranslationFactory;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;

class PostCategoryTranslation extends Model
{
    use HasFactory;
    use SlugTrait;

    protected $table = PostCategoryTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostCategoryTranslationSchema::LANGUAGE_ID,
        PostCategoryTranslationSchema::TITLE,
        PostCategoryTranslationSchema::SLUG,
        PostCategoryTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): PostCategoryTranslationFactory
    {
        return PostCategoryTranslationFactory::new();
    }

    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }
}
