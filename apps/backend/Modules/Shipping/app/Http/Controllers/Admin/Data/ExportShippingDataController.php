<?php

namespace Modules\Shipping\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Shipping\Schemas\Module;
use Modules\Shipping\Transfer\ShippingTransfer;

/**
 * Shipping module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Shipping).
 */
class ExportShippingDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ShippingTransfer::class);
    }
}
