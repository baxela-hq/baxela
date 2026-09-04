<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Exceptions\User\CartItem\OutOfStockException;
use Modules\Cart\Http\Requests\User\CartItem\CreateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartItemAddedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class CreateCartItemAction extends AbstractCartItemAction
{
    public function handle(CreateCartItemRequest $request)
    {
        $cartId = $this->getCartId();

        $cartItem = $this->cartItem
            ->query()
            ->where([
                CartItemSchema::CART_ID => $cartId,
                CartItemSchema::VARIANT_ID => $request->input(CartItemSchema::VARIANT_ID),
            ])->first();

        // Stock gates the whole quantity the cart would hold for the
        // variant (existing + newly added), not just the added amount.
        $desiredQuantity = (int) $request->input(CartItemSchema::QUANTITY)
            + (int) ($cartItem?->{CartItemSchema::QUANTITY} ?? 0);
        $available = app(InventoryGatewayInterface::class)
            ->availableQuantity((string) $request->input(CartItemSchema::VARIANT_ID));
        if (is_null($available) || $available < $desiredQuantity) {
            throw new OutOfStockException(
                VariantDisplayName::for((int) $request->input(CartItemSchema::VARIANT_ID)),
                $available ?? 0,
                (int) $request->input(CartItemSchema::VARIANT_ID),
            );
        }

        if ($cartItem) {
            $cartItem->increment(
                CartItemSchema::QUANTITY,
                $request->input(CartItemSchema::QUANTITY)
            );

            return $cartItem;
        }

        $variant = $this->getVariant($request->input(CartItemSchema::VARIANT_ID));

        $data = [
            CartItemSchema::CART_ID => $cartId,
            CartItemSchema::VARIANT_ID => $request->input(CartItemSchema::VARIANT_ID),
            CartItemSchema::PRICE_SNAPSHOT => $variant->{VariantSchema::PRICE},
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $this->getProductTitle(
                $request->input(CartItemSchema::VARIANT_ID)
            ),
            CartItemSchema::QUANTITY => $request->input(CartItemSchema::QUANTITY),
        ];
        $cartItem = $this->cartItem->query()->create($data);
        $cartItem = $cartItem->fresh();

        event(CartItemAddedEvent::fill($cartItem->toArray()));

        return $cartItem;
    }
}
