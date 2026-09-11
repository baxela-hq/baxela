<?php

namespace Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\User\Schemas\WishlistItem\WishlistItemSchema;

/**
 * @mixin Builder
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
