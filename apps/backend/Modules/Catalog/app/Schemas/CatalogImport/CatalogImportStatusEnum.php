<?php

namespace Modules\Catalog\Schemas\CatalogImport;

enum CatalogImportStatusEnum: string
{
    case COMPLETED = 'completed';

    case FAILED = 'failed';
}
