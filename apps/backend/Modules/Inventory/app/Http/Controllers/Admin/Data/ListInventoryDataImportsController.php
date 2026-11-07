<?php

namespace Modules\Inventory\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Inventory\Schemas\Module;

/**
 * Inventory module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListInventoryDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
