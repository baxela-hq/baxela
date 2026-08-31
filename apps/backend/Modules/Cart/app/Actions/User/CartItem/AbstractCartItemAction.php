<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Utils\Auth;

abstract class AbstractCartItemAction
{
    public function __construct(protected Cart $cart, protected CartItem $cartItem) {}

    protected function getCartId(): int
    {
        $data = [CartSchema::USER_ID => Auth::id()];
        $cart = $this->cart->where($data)->first();

        if (! $cart) {
            $cart = $this->cart->create($data);
            $cart = $cart->refresh();

            event(new CartCreatedEvent(
                $cart->{CartSchema::ID},
                $cart->{CartSchema::USER_ID},
                $cart->{CartSchema::CREATED_AT},
            ));
        }

        return $cart->{CartSchema::ID};
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
