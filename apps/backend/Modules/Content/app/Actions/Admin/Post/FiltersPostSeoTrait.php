<?php

namespace Modules\Content\Actions\Admin\Post;

use Modules\Content\Schemas\Post\PostSeoTranslationSchema;

trait FiltersPostSeoTrait
{
    /**
     * Forms always submit one seo item per language; only items with at
     * least one filled field become rows, and empty strings are stored
     * as null.
     */
    private function filterPostSeo(array $items): array
    {
        $fields = [
            PostSeoTranslationSchema::META_TITLE,
            PostSeoTranslationSchema::META_DESCRIPTION,
            PostSeoTranslationSchema::OPEN_GRAPH_TITLE,
            PostSeoTranslationSchema::OPEN_GRAPH_DESCRIPTION,
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
