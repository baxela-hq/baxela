<?php

namespace Modules\Cart\Actions\Public\CartItem;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Schemas\Language\LanguageSchema;

/**
 * Guest-cart counterpart of the user cart-item actions. The cart is resolved
 * by the X-Cart-Token header instead of the authenticated user, and audience
 * logic is deliberately kept separate from Actions\User\CartItem — guest-cart
 * rules may evolve independently.
 */
abstract class AbstractCartItemAction
{
    public function __construct(protected Cart $cart, protected CartItem $cartItem) {}

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

    protected function getVariant(int $variantId): \stdClass
    {
        return DB::Table(VariantSchema::TABLE)->find($variantId);
    }

    /**
     * Display title of the variant's product: the default language's
     * translation when present, else any translation — snapshotted onto cart
     * items and later onto order lines.
     */
    protected function getProductTitle(int $variantId): string
    {
        $productId = DB::Table(VariantSchema::TABLE)
            ->where(VariantSchema::ID, $variantId)
            ->value(VariantSchema::PRODUCT_ID);

        if (is_null($productId)) {
            return '';
        }

        $translations = DB::Table(ProductTranslationSchema::TABLE)
            ->where(ProductTranslationSchema::PRODUCT_ID, $productId)
            ->get([
                ProductTranslationSchema::LANGUAGE_ID,
                ProductTranslationSchema::TITLE,
            ]);

        if ($translations->isEmpty()) {
            return '';
        }

        $defaultLanguageId = DB::Table(LanguageSchema::TABLE)
            ->where(LanguageSchema::IS_DEFAULT, true)
            ->value(LanguageSchema::ID);

        return $translations
            ->firstWhere(ProductTranslationSchema::LANGUAGE_ID, $defaultLanguageId)
            ?->{ProductTranslationSchema::TITLE}
            ?? $translations->first()->{ProductTranslationSchema::TITLE};
    }
}
