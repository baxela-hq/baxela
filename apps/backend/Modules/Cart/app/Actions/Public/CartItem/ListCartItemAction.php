<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Cart\Support\RefreshesCartPrices;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;

class ListCartItemAction extends AbstractCartItemAction
{
    public function __construct(
        Cart $cart,
        CartItem $cartItem,
        CatalogGatewayInterface $catalogGateway,
        protected RefreshesCartPrices $refreshesCartPrices,
    ) {
        parent::__construct($cart, $cartItem, $catalogGateway);
    }

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

        // Same live price policy as authenticated carts: guest lines also
        // track the current effective (promotion-aware) price
        $this->refreshesCartPrices->refresh($items);

        return $items;
    }
}
