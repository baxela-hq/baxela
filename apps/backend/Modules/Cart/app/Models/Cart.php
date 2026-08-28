<?php

namespace Modules\Cart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Cart\Database\Factories\CartFactory;
use Modules\Cart\Schemas\Cart\CartSchema;

/**
 * @mixin Builder
 */
class Cart extends Model
{
    use HasFactory;

    protected $table = CartSchema::TABLE;

    protected $fillable = [
        CartSchema::USER_ID,
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}
