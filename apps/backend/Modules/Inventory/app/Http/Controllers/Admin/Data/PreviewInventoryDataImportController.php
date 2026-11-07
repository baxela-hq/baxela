<?php

namespace Modules\Inventory\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Inventory\Schemas\Module;
use Modules\Inventory\Transfer\InventoryTransfer;

/**
 * Inventory module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewInventoryDataImportController extends ModuleTransferPreviewController
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
