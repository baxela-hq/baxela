<?php

namespace Modules\Tax\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Tax\Schemas\Module;

/**
 * Tax module's own import-history endpoint — this module's runs from
 * the central transfer audit table, latest first.
 */
class ListTaxDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
