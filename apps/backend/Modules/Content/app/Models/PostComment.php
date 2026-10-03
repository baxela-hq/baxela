<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Content\Database\Factories\PostCommentFactory;
use Modules\Content\Schemas\PostComment\PostCommentSchema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;

/**
 * @mixin Builder
 */
class PostComment extends Model
{
    use HasFactory;

    protected $table = PostCommentSchema::TABLE;

    protected $fillable = [
        PostCommentSchema::POST_ID,
        PostCommentSchema::USER_ID,
        PostCommentSchema::PARENT_ID,
        PostCommentSchema::BODY,
        PostCommentSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            PostCommentSchema::STATUS => PostCommentStatusEnum::class,
        ];
    }

    protected static function newFactory(): PostCommentFactory
    {
        return PostCommentFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, PostCommentSchema::PARENT_ID);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, PostCommentSchema::PARENT_ID);
    }
}
