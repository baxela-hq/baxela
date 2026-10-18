<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\ProductCommentFactory;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;

/**
 * @mixin Builder
 */
class ProductComment extends Model
{
    use HasFactory;

    protected $table = ProductCommentSchema::TABLE;

    protected $fillable = [
        ProductCommentSchema::PRODUCT_ID,
        ProductCommentSchema::USER_ID,
        ProductCommentSchema::PARENT_ID,
        ProductCommentSchema::BODY,
        ProductCommentSchema::RATING,
        ProductCommentSchema::STATUS,
    ];

    protected function casts(): array
    {
        return [
            ProductCommentSchema::RATING => 'integer',
            ProductCommentSchema::STATUS => ProductCommentStatusEnum::class,
        ];
    }

    protected static function newFactory(): ProductCommentFactory
    {
        return ProductCommentFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, ProductCommentSchema::PARENT_ID);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, ProductCommentSchema::PARENT_ID);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ProductCommentReaction::class, ProductCommentReactionSchema::PRODUCT_COMMENT_ID);
    }
}
