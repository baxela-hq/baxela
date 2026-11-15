<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema;

/**
 * @mixin Builder
 */
/**
 * @property int $id
 */
class ProductCommentReaction extends Model
{
    protected $table = ProductCommentReactionSchema::TABLE;

    protected $fillable = [
        ProductCommentReactionSchema::PRODUCT_COMMENT_ID,
        ProductCommentReactionSchema::USER_ID,
        ProductCommentReactionSchema::REACTION,
    ];

    protected function casts(): array
    {
        return [
            ProductCommentReactionSchema::REACTION => ProductCommentReactionEnum::class,
        ];
    }

    /** @return BelongsTo<ProductComment, $this> */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(ProductComment::class, ProductCommentReactionSchema::PRODUCT_COMMENT_ID);
    }
}
