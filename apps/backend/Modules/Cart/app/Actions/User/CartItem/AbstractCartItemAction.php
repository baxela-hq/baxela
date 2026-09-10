<?php

namespace Modules\Cart\Actions\User\CartItem;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;
use Modules\Core\Utils\Auth;

abstract class AbstractCartItemAction
{
    public function __construct(
        protected Cart $cart,
        protected CartItem $cartItem,
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    protected function getCartId(): int
    {
        $data = [CartSchema::USER_ID => Auth::id()];
        $cart = $this->cart->where($data)->first();

        if (! $cart) {
            $cart = $this->cart->create($data);
            $cart = $cart->refresh();

            event(CartCreatedEvent::fill([
                CartSchema::ID => $cart->{CartSchema::ID},
                CartSchema::USER_ID => $cart->{CartSchema::USER_ID},
                CartSchema::UPDATED_AT => $cart->{CartSchema::UPDATED_AT},
            ]));
        }

        return $cart->{CartSchema::ID};
    }

    /**
     * Stock-error name ("Product (Size / Black)") resolved through the
     * Catalog gateway — empty when the variant no longer resolves.
     */
    protected function variantDisplayName(int $variantId): string
    {
        return VariantDisplayName::fromSummary(
            $this->catalogGateway->getVariantSummaries([$variantId])->get($variantId)
        );
    }
}
