<?php

namespace Modules\Order\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Order\Schemas\Module;

/**
 * Order module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListOrderDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
