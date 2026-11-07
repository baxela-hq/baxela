<?php

namespace Modules\Core\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\Core\Schemas\Module;
use Modules\Core\Transfer\CoreTransfer;

/**
 * Core module's own data export endpoint — reference data
 * (languages/currencies/countries) as a transfer file.
 */
class ExportCoreDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(CoreTransfer::class);
    }
}
