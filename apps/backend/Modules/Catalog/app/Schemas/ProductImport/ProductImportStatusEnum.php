<?php

namespace Modules\Catalog\Schemas\ProductImport;

enum ProductImportStatusEnum: string
{
    case COMPLETED = 'completed';

    case FAILED = 'failed';
}
