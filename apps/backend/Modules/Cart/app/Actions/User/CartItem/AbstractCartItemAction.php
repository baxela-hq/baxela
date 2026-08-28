<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Utils\Auth;

abstract class AbstractCartItemAction
{
    public function __construct(protected Cart $cart, protected CartItem $cartItem) {}

    protected function getCartId(): int
    {
        $data = [CartSchema::USER_ID => Auth::id()];
        $cart = $this->cart->where($data)->first();

        if (! $cart) {
            $cart = $this->cart->create($data);
            $cart = $cart->refresh();

            event(new CartCreatedEvent(
                $cart->{CartSchema::ID},
                $cart->{CartSchema::USER_ID},
                $cart->{CartSchema::CREATED_AT},
            ));
        }

        return $cart->{CartSchema::ID};
    }

    protected function getVariant(int $variantId): \stdClass
    {
        return DB::Table(VariantSchema::TABLE)->find($variantId);
    }
}
