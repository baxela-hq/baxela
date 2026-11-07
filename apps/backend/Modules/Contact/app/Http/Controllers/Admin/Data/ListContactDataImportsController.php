<?php

namespace Modules\Contact\Http\Controllers\Admin\Data;

use Modules\Contact\Schemas\Module;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;

/**
 * Contact module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListContactDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
