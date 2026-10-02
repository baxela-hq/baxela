<?php

namespace Modules\Catalog\Schemas\CatalogImport;

enum CatalogImportStrategyEnum: string
{
    case UPDATE = 'update';

    case SKIP = 'skip';
}
