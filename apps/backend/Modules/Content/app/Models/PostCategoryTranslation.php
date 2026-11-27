<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostCategoryTranslationFactory;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $post_category_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read PostCategory $postCategory
 */
class PostCategoryTranslation extends Model
{
    /** @use HasFactory<PostCategoryTranslationFactory> */
    use HasFactory;

    use SlugTrait;
    use TenantScopedTrait;

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

    /** @return BelongsTo<PostCategory, $this> */
    public function postCategory(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class);
    }
}
