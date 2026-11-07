<?php

namespace Modules\Support\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Support\Schemas\Module;
use Modules\Support\Transfer\SupportTransfer;

/**
 * Support module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Support).
 */
class ExportSupportDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(SupportTransfer::class);
    }
}
