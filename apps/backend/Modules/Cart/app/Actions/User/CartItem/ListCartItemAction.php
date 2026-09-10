<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Schemas\CartItem\CartItemSchema;

class ListCartItemAction extends AbstractCartItemAction
{
    public function handle()
    {
        $items = $this->cartItem
            ->where(CartItemSchema::CART_ID, $this->getCartId())
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
