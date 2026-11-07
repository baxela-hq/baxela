<?php

namespace Modules\User\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\User\Schemas\Module;

/**
 * User module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListUserDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
