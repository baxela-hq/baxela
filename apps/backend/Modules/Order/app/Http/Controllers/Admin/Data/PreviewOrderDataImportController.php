<?php

namespace Modules\Order\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Order\Schemas\Module;
use Modules\Order\Transfer\OrderTransfer;

/**
 * Order module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewOrderDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(OrderTransfer::class);
    }
}
