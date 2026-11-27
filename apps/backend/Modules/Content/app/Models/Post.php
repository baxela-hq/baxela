<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Content\Database\Factories\PostFactory;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\PostCategory\PostCategoryPostSchema;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @property int $id
 * @property PostStatusEnum $status
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, PostTranslation> $translations
 * @property-read Collection<int, PostCategory> $categories
 * @property-read Collection<int, PostProduct> $products
 * @property-read Collection<int, PostSeoTranslation> $seo
 * @property-read Collection<int, PostComment> $comments
 * @property int|null $image_media_id
 * @property string|null $image_url
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use TenantScopedTrait;

    protected $table = PostSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostSchema::STATUS,
        PostSchema::PUBLISHED_AT,
        PostSchema::IMAGE_MEDIA_ID,
        PostSchema::IMAGE_URL,
    ];

    protected function casts(): array
    {
        return [
            PostSchema::STATUS => PostStatusEnum::class,
            PostSchema::PUBLISHED_AT => 'datetime',
        ];
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    /** @return HasMany<PostTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    /** @return BelongsToMany<PostCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, PostCategoryPostSchema::TABLE,
            PostCategoryPostSchema::POST_ID, PostCategoryPostSchema::POST_CATEGORY_ID);
    }

    /** @return HasMany<PostProduct, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(PostProduct::class);
    }

    /** @return HasMany<PostSeoTranslation, $this> */
    public function seo(): HasMany
    {
        return $this->hasMany(PostSeoTranslation::class);
    }

    /** @return HasMany<PostComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /** @return MorphOne<FeaturedItem, $this> */
    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }
}
