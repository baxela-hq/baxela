<?php

namespace Modules\Notification\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Notification\Schemas\Module;
use Modules\Notification\Transfer\NotificationTransfer;

/**
 * Notification module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewNotificationDataImportController extends ModuleTransferPreviewController
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
