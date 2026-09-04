<?php

namespace Modules\Cart\Support;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema;
use Modules\Catalog\Schemas\Product\ProductTranslationSchema;
use Modules\Catalog\Schemas\Variant\VariantOptionValueSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Schemas\Language\LanguageSchema;

class VariantDisplayName
{
    /**
     * Display name for stock errors: product title plus the variant's
     * option values ("Men's Running Shoes (Shoe Size 10 / Black)") —
     * default-language titles, falling back to any translation.
     */
    public static function for(int $variantId): string
    {
        $title = self::productTitle($variantId);

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

    private static function productTitle(int $variantId): string
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
