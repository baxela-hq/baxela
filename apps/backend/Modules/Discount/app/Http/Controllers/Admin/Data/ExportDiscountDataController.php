<?php

namespace Modules\Discount\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Discount\Schemas\Module;
use Modules\Discount\Transfer\DiscountTransfer;

/**
 * Discount module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Discount).
 */
class ExportDiscountDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(DiscountTransfer::class);
    }
}
