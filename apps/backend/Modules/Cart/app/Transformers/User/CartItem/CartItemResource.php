<?php

namespace Modules\Cart\Transformers\User\CartItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Cart\Schemas\CartItem\CartItemSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class CartItemResource extends JsonResource
{
    use ResolvesPublicLanguage;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            CartItemSchema::ID => $this->resource->{CartItemSchema::ID},
            CartItemSchema::VARIANT_ID => $this->resource->{CartItemSchema::VARIANT_ID},
            CartItemSchema::CART_ID => $this->resource->{CartItemSchema::CART_ID},
            CartItemSchema::PRICE_SNAPSHOT => $this->resource->{CartItemSchema::PRICE_SNAPSHOT},
            CartItemSchema::PRODUCT_NAME_SNAPSHOT => $this->resource->{CartItemSchema::PRODUCT_NAME_SNAPSHOT},
            CartItemSchema::VARIANT_LABEL => $this->whenLoaded(
                CartItemSchema::RES_VARIANT,
                fn () => $this->variantLabel($request)
            ),
            CartItemSchema::QUANTITY => $this->resource->{CartItemSchema::QUANTITY},
            CartItemSchema::CREATED_AT => $this->resource->{CartItemSchema::CREATED_AT},
            CartItemSchema::UPDATED_AT => $this->resource->{CartItemSchema::UPDATED_AT},
        ];
    }

    /**
     * The variant's option values joined for display ("S / Black"), in the
     * request language with a first-translation fallback; null when the
     * variant has no option values.
     */
    private function variantLabel(Request $request): ?string
    {
        $languageId = $this->resolvePublicLanguageId($request);

        $titles = Collection::wrap(
            $this->resource->{CartItemSchema::RES_VARIANT}->{VariantSchema::RES_OPTION_VALUES} ?? []
        )->map(function ($optionValue) use ($languageId, $request): ?string {
            $translation = $optionValue->translations
                ->firstWhere(OVTSchema::LANGUAGE_ID, $languageId)
                ?? $optionValue->translations->first();

            return $translation?->{OVTSchema::TITLE};
        })->filter()->values();

        return $titles->isNotEmpty() ? $titles->join(' / ') : null;
    }
}
