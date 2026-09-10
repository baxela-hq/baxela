<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;

class ListCartItemAction extends AbstractCartItemAction
{
    /**
     * A guest without a cart simply has an empty cart — no row is created
     * just by viewing it.
     */
    public function handle(string $token)
    {
        $cartId = $this->findCartId($token);
        if ($cartId === null) {
            return $this->cartItem->newCollection();
        }

        $items = $this->cartItem
            ->where(CartItemSchema::CART_ID, $cartId)
            ->get();

        // Variant display data (label, product link fields) is resolved in
        // one batched gateway call and attached for the resource.
        $summaries = $this->catalogGateway->getVariantSummaries(
            $items->pluck(CartItemSchema::VARIANT_ID)->all()
        );
        $items->each(fn ($item) => $item->setAttribute(
            CartItemSchema::ATTR_VARIANT_SUMMARY,
            $summaries->get($item->{CartItemSchema::VARIANT_ID}),
        ));

        return $items;
    }
}
