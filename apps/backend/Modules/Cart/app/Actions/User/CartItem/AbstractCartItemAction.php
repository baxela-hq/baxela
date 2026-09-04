<?php

namespace Modules\Cart\Actions\User\CartItem;

use Illuminate\Support\Facades\DB;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Cart\Schemas\Cart\CartSchema;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Events\Cart\CartCreatedEvent;
use Modules\Core\Schemas\Language\LanguageSchema;
use Modules\Core\Utils\Auth;

abstract class AbstractCartItemAction
{
    public function __construct(protected Cart $cart, protected CartItem $cartItem) {}

    /**
     * Display name for stock errors: product title plus the variant's
     * option values ("Men's Running Shoes (Shoe Size 10 / Black)") —
     * default-language titles, mirroring getProductTitle.
     */
    protected function variantDisplayName(int $variantId): string
    {
        $title = $this->getProductTitle($variantId);

        $defaultLanguageId = DB::Table(LanguageSchema::TABLE)
            ->where(LanguageSchema::IS_DEFAULT, true)
            ->value(LanguageSchema::ID);

        $optionValueIds = DB::Table(VariantOptionValueSchema::TABLE)
            ->where(VariantOptionValueSchema::VARIANT_ID, $variantId)
            ->pluck(VariantOptionValueSchema::OPTION_VALUE_ID);

        $labels = $optionValueIds
            ->map(function (int $optionValueId) use ($defaultLanguageId): ?string {
                $translations = DB::Table(OptionValueTranslationSchema::TABLE)
                    ->where(OptionValueTranslationSchema::OPTION_VALUE_ID, $optionValueId)
                    ->get([
                        OptionValueTranslationSchema::LANGUAGE_ID,
                        OptionValueTranslationSchema::TITLE,
                    ]);

                return $translations
                    ->firstWhere(OptionValueTranslationSchema::LANGUAGE_ID, $defaultLanguageId)
                    ?->{OptionValueTranslationSchema::TITLE}
                    ?? $translations->first()?->{OptionValueTranslationSchema::TITLE};
            })
            ->filter()
            ->values();

        $label = $labels->isNotEmpty() ? " ({$labels->join(' / ')})" : '';

        return $title.$label;
    }

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
