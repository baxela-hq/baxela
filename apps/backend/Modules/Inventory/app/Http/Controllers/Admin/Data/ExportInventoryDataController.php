<?php

namespace Modules\Inventory\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Inventory\Schemas\Module;
use Modules\Inventory\Transfer\InventoryTransfer;

/**
 * Inventory module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Inventory).
 */
class ExportInventoryDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(InventoryTransfer::class);
    }
}
