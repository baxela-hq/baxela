<?php

namespace Modules\Setting\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Setting\Schemas\Module;

/**
 * Setting module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListSettingDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
