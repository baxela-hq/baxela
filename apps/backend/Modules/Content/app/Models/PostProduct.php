<?php

namespace Modules\Content\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Schemas\Post\PostProductSchema;

/**
 * Related-product pivot row. Deliberately has no relation to the Catalog
 * Product model — products resolve through the Catalog gateway only.
 */
/**
 * @property int $id
 * @property int $post_id
 * @property int $product_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Post $post
 */
class PostProduct extends Model
{
    public $timestamps = false;

    protected $table = PostProductSchema::TABLE;

    protected $fillable = [
        PostProductSchema::POST_ID,
        PostProductSchema::PRODUCT_ID,
    ];

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
