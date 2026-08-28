<?php

namespace Modules\Cart\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Cart\Database\Factories\CartItemFactory;
use Modules\Cart\Schemas\CartItem\CartItemSchema;

// use Modules\Cart\Database\Factories\CartItemFactory;

class CartItem extends Model
{
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

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected static function newFactory(): CartItemFactory
    {
        return CartItemFactory::new();
    }
}
