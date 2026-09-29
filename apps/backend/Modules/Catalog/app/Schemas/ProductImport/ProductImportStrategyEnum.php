<?php

namespace Modules\Catalog\Schemas\ProductImport;

enum ProductImportStrategyEnum: string
{
    case UPDATE = 'update';

    case SKIP = 'skip';
}
