<?php

namespace Modules\Tax\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Tax\Schemas\Module;
use Modules\Tax\Transfer\TaxTransfer;

/**
 * Tax module's own data export endpoint — the module-bound subclass of
 * the shared Core file plane (microservice-ready: travels with Tax).
 */
class ExportTaxDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(TaxTransfer::class);
    }
}
