<?php

namespace Modules\Menu\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Menu\Schemas\Module;

/**
 * Menu module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListMenuDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
