<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostSeoTranslationFactory;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;

class PostSeoTranslation extends Model
{
    use HasFactory;

    protected $table = PostSeoTranslationSchema::TABLE;

    protected $fillable = [
        PostSeoTranslationSchema::POST_ID,
        PostSeoTranslationSchema::LANGUAGE_ID,
        PostSeoTranslationSchema::META_TITLE,
        PostSeoTranslationSchema::META_DESCRIPTION,
        PostSeoTranslationSchema::OPEN_GRAPH_TITLE,
        PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION,
    ];

    protected static function newFactory(): PostSeoTranslationFactory
    {
        return PostSeoTranslationFactory::new();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
