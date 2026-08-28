<?php

namespace Modules\Core\Contracts\Events\Cart;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class CartItemRemovedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $cart_id;

    public int $variant_id;

    public int $quantity;

    public float $price_snapshot;

    public string $product_name_snapshot;

    public string $created_at;
}
