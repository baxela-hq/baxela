<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Content\Database\Factories\PostFactory;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\PostCategory\PostCategoryPostSchema;

class Post extends Model
{
    use HasFactory;

    protected $table = PostSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        PostSchema::STATUS,
        PostSchema::PUBLISHED_AT,
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

    public function translations(): HasMany
    {
        return $this->hasMany(PostTranslation::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(PostCategory::class, PostCategoryPostSchema::TABLE,
            PostCategoryPostSchema::POST_ID, PostCategoryPostSchema::POST_CATEGORY_ID);
    }

    public function products(): HasMany
    {
        return $this->hasMany(PostProduct::class);
    }

    public function seo(): HasMany
    {
        return $this->hasMany(PostSeoTranslation::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function featuredItem(): MorphOne
    {
        return $this->morphOne(FeaturedItem::class, 'featuredable');
    }
}
