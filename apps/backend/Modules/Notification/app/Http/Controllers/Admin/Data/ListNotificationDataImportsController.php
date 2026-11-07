<?php

namespace Modules\Notification\Http\Controllers\Admin\Data;

use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportsListController;
use Modules\Notification\Schemas\Module;

/**
 * Notification module's own import-history endpoint — this module's runs
 * from the central transfer audit table, latest first.
 */
class ListNotificationDataImportsController extends ModuleTransferImportsListController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }
}
