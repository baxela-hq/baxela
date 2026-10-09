<?php

namespace Modules\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Content\Schemas\Post\PostProductSchema;

/**
 * Related-product pivot row. Deliberately has no relation to the Catalog
 * Product model — products resolve through the Catalog gateway only.
 */
class PostProduct extends Model
{
    public $timestamps = false;

    protected $table = PostProductSchema::TABLE;

    protected $fillable = [
        PostProductSchema::POST_ID,
        PostProductSchema::PRODUCT_ID,
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
