<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostImageFactory;
use Modules\Content\Schemas\Post\PostImageCollectionEnum;
use Modules\Content\Schemas\Post\PostImageSchema;

class PostImage extends Model
{
    use HasFactory;

    protected $table = PostImageSchema::TABLE;

    protected $fillable = [
        PostImageSchema::POST_ID,
        PostImageSchema::MEDIA_ID,
        PostImageSchema::URL,
        PostImageSchema::COLLECTION,
        PostImageSchema::POSITION,
    ];

    public function casts(): array
    {
        return [
            PostImageSchema::COLLECTION => PostImageCollectionEnum::class,
        ];
    }

    protected static function newFactory(): PostImageFactory
    {
        return PostImageFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
