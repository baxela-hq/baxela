<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Database\Factories\PostSeoTranslationFactory;
use Modules\Content\Schemas\Post\PostSeoTranslationSchema;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $language_id
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $open_graph_title
 * @property string|null $open_graph_description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Post $post
 */
class PostSeoTranslation extends Model
{
    /** @use HasFactory<PostSeoTranslationFactory> */
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

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
