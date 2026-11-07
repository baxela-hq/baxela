<?php

namespace Modules\Cart\Http\Controllers\Admin\Data;

use Modules\Cart\Schemas\Module;
use Modules\Cart\Transfer\CartTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;

/**
 * Cart module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Cart).
 */
class ExportCartDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(CartTransfer::class);
    }
}
