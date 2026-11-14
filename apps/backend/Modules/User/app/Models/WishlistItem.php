<?php

namespace Modules\User\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

/**
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 *
 * @mixin Builder<WishlistItem>
 */
class WishlistItem extends Model
{
    protected $table = WishlistItemSchema::TABLE;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        WishlistItemSchema::USER_ID,
        WishlistItemSchema::PRODUCT_ID,
    ];
}
