<?php

namespace Modules\Catalog\Actions\Admin\Product;

use Modules\Catalog\Schemas\Product\ProductSeoTranslationSchema;

trait FiltersProductSeoTrait
{
    /**
     * Forms always submit one seo item per language; only items with at
     * least one filled field become rows, and empty strings are stored
     * as null.
     */
    private function filterProductSeo(array $items): array
    {
        $fields = [
            ProductSeoTranslationSchema::META_TITLE,
            ProductSeoTranslationSchema::META_DESCRIPTION,
            ProductSeoTranslationSchema::OPEN_GRAPH_TITLE,
            ProductSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION,
        ];

        return collect($items)
            ->map(function (array $item) use ($fields) {
                foreach ($fields as $field) {
                    $item[$field] = ($item[$field] ?? null) === '' ? null : $item[$field] ?? null;
                }

                return $item;
            })
            ->filter(function (array $item) use ($fields) {
                foreach ($fields as $field) {
                    if ($item[$field] !== null) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }
}
