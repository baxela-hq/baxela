<?php

namespace Modules\Catalog\Schemas\CatalogImport;

/**
 * What a catalog_imports row was produced by: the product CSV import
 * or the whole-module JSON data transfer.
 */
enum CatalogImportEntityEnum: string
{
    case PRODUCT = 'product';

    case CATALOG = 'catalog';
}
