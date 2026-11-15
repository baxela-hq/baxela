<?php

namespace Modules\Cart\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cart\Database\Factories\CartItemFactory;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

/**
 * @mixin Builder
 */
/**
 * @property int $id
 * @property int $cart_id
 * @property int $variant_id
 * @property string $price_snapshot
 * @property string $product_name_snapshot
 * @property int $quantity
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Cart $cart
 */
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $table = CartItemSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        CartItemSchema::CART_ID,
        CartItemSchema::VARIANT_ID,
        CartItemSchema::PRICE_SNAPSHOT,
        CartItemSchema::PRODUCT_NAME_SNAPSHOT,
        CartItemSchema::QUANTITY,
    ];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
