<?php

namespace Modules\Order\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Order\Schemas\Module;
use Modules\Order\Transfer\OrderTransfer;

/**
 * Order module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Order).
 */
class ExportOrderDataController extends ModuleTransferExportController
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
