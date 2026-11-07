<?php

namespace Modules\Notification\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Transfer\NotificationTransfer;

/**
 * Notification module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Notification).
 */
class ExportNotificationDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(NotificationTransfer::class);
    }
}
