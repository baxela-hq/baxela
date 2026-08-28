<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Http\Requests\User\CartItem\CreateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartItemAddedEvent;

class CreateCartItemAction extends AbstractCartItemAction
{
    public function handle(CreateCartItemRequest $request)
    {
        $cartItem = $this->cartItem
            ->query()
            ->where([CartItemSchema::VARIANT_ID => $request->input(CartItemSchema::VARIANT_ID)])->first();

        if (! $cartItem) {
            $cartId = $this->getCartId();
            $variant = $this->getVariant($request->input(CartItemSchema::VARIANT_ID));

            $data = [
                CartItemSchema::CART_ID => $cartId,
                CartItemSchema::VARIANT_ID => $request->input(CartItemSchema::VARIANT_ID),
                CartItemSchema::PRICE_SNAPSHOT => $variant->{VariantSchema::PRICE},
                CartItemSchema::PRODUCT_NAME_SNAPSHOT => 'TEST',
                CartItemSchema::QUANTITY => $request->input(CartItemSchema::QUANTITY),
            ];
            $cartItem = $this->cartItem->query()->create($data);
            $cartItem = $cartItem->fresh();

            event(CartItemAddedEvent::fill($cartItem->toArray()));
        }

        return $cartItem;
    }
}
