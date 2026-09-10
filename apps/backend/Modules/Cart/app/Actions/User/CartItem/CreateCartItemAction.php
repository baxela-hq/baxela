<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Exceptions\User\CartItem\OutOfStockException;
use Modules\Cart\Http\Requests\User\CartItem\CreateCartItemRequest;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Core\Contracts\Events\Cart\CartItemAddedEvent;
use Modules\Core\Contracts\Gateways\Inventory\InventoryGatewayInterface;

class CreateCartItemAction extends AbstractCartItemAction
{
    public function handle(CreateCartItemRequest $request)
    {
        $cartId = $this->getCartId();
        $variantId = (int) $request->input(CartItemSchema::VARIANT_ID);

        $cartItem = $this->cartItem
            ->query()
            ->where([
                CartItemSchema::CART_ID => $cartId,
                CartItemSchema::VARIANT_ID => $variantId,
            ])->first();

        // Stock gates the whole quantity the cart would hold for the
        // variant (existing + newly added), not just the added amount.
        $desiredQuantity = (int) $request->input(CartItemSchema::QUANTITY)
            + (int) ($cartItem?->{CartItemSchema::QUANTITY} ?? 0);
        $available = app(InventoryGatewayInterface::class)
            ->availableQuantity((string) $variantId);
        if (is_null($available) || $available < $desiredQuantity) {
            throw new OutOfStockException(
                $this->variantDisplayName($variantId),
                $available ?? 0,
                $variantId,
            );
        }

        if ($cartItem) {
            $cartItem->increment(
                CartItemSchema::QUANTITY,
                $request->input(CartItemSchema::QUANTITY)
            );

            return $cartItem;
        }

        // Snapshots (price, request-language product name) come from the
        // Catalog gateway — an unresolvable variant is treated as
        // unavailable rather than crashing on missing data.
        $summary = $this->catalogGateway->getVariantSummaries([$variantId])->get($variantId);
        if ($summary === null) {
            throw new OutOfStockException('', 0, $variantId);
        }

        $data = [
            CartItemSchema::CART_ID => $cartId,
            CartItemSchema::VARIANT_ID => $variantId,
            CartItemSchema::PRICE_SNAPSHOT => $summary->price,
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $summary->product_title ?? '',
            CartItemSchema::QUANTITY => $request->input(CartItemSchema::QUANTITY),
        ];
        $cartItem = $this->cartItem->query()->create($data);
        $cartItem = $cartItem->fresh();

        event(CartItemAddedEvent::fill($cartItem->toArray()));

        return $cartItem;
    }
}
