<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
/**
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property PostCommentStatusEnum $status
 * @property string|null $body
 * @property int|null $parent_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Post $post
 * @property-read PostComment|null $parent
 * @property-read Collection<int, PostComment> $replies
 */
class PostComment extends Model
{
    /** @use HasFactory<PostCommentFactory> */
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

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<PostComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, PostCommentSchema::PARENT_ID);
    }

    /** @return HasMany<PostComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, PostCommentSchema::PARENT_ID);
    }
}
