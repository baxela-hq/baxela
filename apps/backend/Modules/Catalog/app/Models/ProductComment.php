<?php

namespace Modules\Catalog\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Database\Factories\ProductCommentFactory;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Core\Models\Traits\TenantScopedTrait;

/**
 * @mixin Builder<ProductComment>
 */
/**
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $rating
 * @property ProductCommentStatusEnum $status
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class ProductComment extends Model
{
    /** @use HasFactory<ProductCommentFactory> */
    use HasFactory;

    use TenantScopedTrait;

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

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductComment, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, ProductCommentSchema::PARENT_ID);
    }

    /** @return HasMany<ProductComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, ProductCommentSchema::PARENT_ID);
    }

    /** @return HasMany<ProductCommentReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(ProductCommentReaction::class, ProductCommentReactionSchema::PRODUCT_COMMENT_ID);
    }
}
