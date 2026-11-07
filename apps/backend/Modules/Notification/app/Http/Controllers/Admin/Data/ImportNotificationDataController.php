<?php

namespace Modules\Notification\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Transfer\NotificationTransfer;

/**
 * Notification module's own import endpoint — replays an uploaded transfer
 * file through NotificationTransfer; dry runs validate without writing.
 */
class ImportNotificationDataController extends ModuleTransferImportController
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
