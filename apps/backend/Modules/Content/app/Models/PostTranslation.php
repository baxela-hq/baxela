<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostTranslationFactory;
use Modules\Content\Schemas\Post\PostTranslationSchema;
use Modules\Core\Models\Traits\SlugTrait;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $language_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Post $post
 */
class PostTranslation extends Model
{
    /** @use HasFactory<PostTranslationFactory> */
    use HasFactory;

    use SlugTrait;
    use TenantScopedTrait;

    protected $table = PostTranslationSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostTranslationSchema::LANGUAGE_ID,
        PostTranslationSchema::TITLE,
        PostTranslationSchema::SLUG,
        PostTranslationSchema::CONTENT,
        PostTranslationSchema::DESCRIPTION,
    ];

    protected static function newFactory(): PostTranslationFactory
    {
        return PostTranslationFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
