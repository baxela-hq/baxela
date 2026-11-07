<?php

namespace Modules\Payment\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Payment\Schemas\Module;

/**
 * Payment module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListPaymentDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
