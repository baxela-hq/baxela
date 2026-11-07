<?php

namespace Modules\Core\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Core\Schemas\Module;

/**
 * Core module's own import-history endpoint.
 */
class ListCoreDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
