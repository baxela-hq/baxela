<?php

namespace Modules\Setting\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Setting\Schemas\Module;
use Modules\Setting\Transfer\SettingTransfer;

/**
 * Setting module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Setting).
 */
class ExportSettingDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(SettingTransfer::class);
    }
}
