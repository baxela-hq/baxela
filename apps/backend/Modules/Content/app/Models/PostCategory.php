<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Content\Database\Factories\PostCategoryFactory;
use Modules\Content\Schemas\PostCategory\PostCategoryPostSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Post> $posts
 * @property-read Collection<int, PostCategoryTranslation> $translations
 * @property int|null $parent_id
 * @property int|null $position
 */
class PostCategory extends Model
{
    /** @use HasFactory<PostCategoryFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = PostCategorySchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostCategorySchema::PARENT_ID,
        PostCategorySchema::POSITION,
    ];

    protected static function newFactory(): PostCategoryFactory
    {
        return PostCategoryFactory::new();
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, PostCategoryPostSchema::TABLE,
            PostCategoryPostSchema::POST_CATEGORY_ID, PostCategoryPostSchema::POST_ID);
    }

    /** @return HasMany<PostCategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PostCategoryTranslation::class);
    }
}
