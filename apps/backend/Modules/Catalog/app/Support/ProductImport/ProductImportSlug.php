<?php

namespace Modules\Catalog\Support\ProductImport;

use Modules\Core\Support\Slug;

/**
 * Import-specific slug fallbacks on top of the shared Unicode slugifier.
 */
final class ProductImportSlug
{
    public static function slugify(string $value): string
    {
        return Slug::slugify($value);
    }

    /**
     * Slug for a row whose title yields nothing (e.g. an all-symbol
     * title): fall back to the SKU so the row stays addressable.
     */
    public static function forTitle(string $title, string $sku): string
    {
        $slug = Slug::slugify($title);

        return $slug !== '' ? $slug : Slug::slugify('product-'.$sku);
    }
}
