<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Content\Database\Factories\PostCategoryFactory;
use Modules\Content\Schemas\PostCategory\PostCategoryPostSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class PostCategory extends Model
{
    use HasFactory;

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

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, PostCategoryPostSchema::TABLE,
            PostCategoryPostSchema::POST_CATEGORY_ID, PostCategoryPostSchema::POST_ID);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PostCategoryTranslation::class);
    }
}
