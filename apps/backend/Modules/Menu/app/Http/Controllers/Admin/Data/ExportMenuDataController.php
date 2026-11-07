<?php

namespace Modules\Menu\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Menu\Schemas\Module;
use Modules\Menu\Transfer\MenuTransfer;

/**
 * Menu module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Menu).
 */
class ExportMenuDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(MenuTransfer::class);
    }
}
