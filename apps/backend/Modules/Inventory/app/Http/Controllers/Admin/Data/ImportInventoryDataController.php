<?php

namespace Modules\Inventory\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Inventory\Schemas\Module;
use Modules\Inventory\Transfer\InventoryTransfer;

/**
 * Inventory module's own import endpoint — replays an uploaded transfer
 * file through InventoryTransfer; dry runs validate without writing.
 */
class ImportInventoryDataController extends ModuleTransferImportController
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
