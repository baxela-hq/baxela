<?php

namespace Modules\Cart\Support;

use Modules\Core\Contracts\Gateways\Catalog\DTOs\VariantSummary;

class VariantDisplayName
{
    /**
     * Display name for stock errors, composed from a gateway variant
     * summary: "Men's Running Shoes (Shoe Size 10 / Black)". An empty
     * string when the variant no longer resolves.
     */
    public static function fromSummary(?VariantSummary $summary): string
    {
        if ($summary === null) {
            return '';
        }

        if ($summary->variant_label === null) {
            return (string) $summary->product_title;
        }

        return $summary->product_title.' ('.$summary->variant_label.')';
    }
}
