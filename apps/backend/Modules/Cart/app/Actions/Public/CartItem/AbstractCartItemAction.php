<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Support\VariantDisplayName;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Contracts\Gateways\Catalog\CatalogGatewayInterface;

/**
 * Guest-cart counterpart of the user cart-item actions. The cart is resolved
 * by the X-Cart-Token header instead of the authenticated user, and audience
 * logic is deliberately kept separate from Actions\User\CartItem — guest-cart
 * rules may evolve independently.
 */
abstract class AbstractCartItemAction
{
    public function __construct(
        protected Cart $cart,
        protected CartItem $cartItem,
        protected CatalogGatewayInterface $catalogGateway,
    ) {}

    /**
     * Resolve (and lazily create) the cart for the bound guest token. Only
     * called when adding an item — the first item is what brings the cart
     * into existence; listing never creates rows.
     */
    protected function getCartId(string $token): int
    {
        $cart = $this->cart->query()->where(CartSchema::TOKEN, $token)->first();

        if (! $cart) {
            $cart = $this->cart->query()->create([CartSchema::TOKEN => $token]);
            $cart = $cart->refresh();

            event(CartCreatedEvent::fill([
                CartSchema::ID => $cart->{CartSchema::ID},
                CartSchema::USER_ID => null,
                CartSchema::UPDATED_AT => $cart->{CartSchema::UPDATED_AT},
            ]));
        }

        return $cart->{CartSchema::ID};
    }

    /**
     * Read-only cart lookup — null when the guest has no cart yet (list
     * renders an empty cart; update/delete surface a 404).
     */
    protected function findCartId(string $token): ?int
    {
        return $this->cart->query()
            ->where(CartSchema::TOKEN, $token)
            ->value(CartSchema::ID);
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
