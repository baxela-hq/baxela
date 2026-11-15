<?php

namespace Modules\Cart\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Cart\Database\Factories\CartFactory;
use Modules\Cart\Schemas\Cart\CartSchema;

/**
 * @mixin Builder
 */
/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $token
 * @property string|null $coupon_code
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, CartItem> $items
 */
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $table = CartSchema::TABLE;

    protected $fillable = [
        CartSchema::USER_ID,
        CartSchema::TOKEN,
        CartSchema::COUPON_CODE,
    ];

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}
