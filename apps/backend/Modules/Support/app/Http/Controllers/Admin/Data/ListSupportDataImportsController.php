<?php

namespace Modules\Support\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Support\Schemas\Module;

/**
 * Support module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListSupportDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
