<?php

namespace Modules\Cart\Actions\User\CartItem;

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

    public function handle()
    {
        $items = $this->cartItem
            ->where(CartItemSchema::CART_ID, $this->getCartId())
            ->get();

        // Prices are re-resolved live (promotions included): snapshots are
        // refreshed to the current effective price and the summary is
        // attached for the resource in the same pass
        $this->refreshesCartPrices->refresh($items);

        return $items;
    }
}
