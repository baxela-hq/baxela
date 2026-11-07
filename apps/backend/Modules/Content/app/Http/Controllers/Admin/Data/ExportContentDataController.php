<?php

namespace Modules\Content\Http\Controllers\Admin\Data;

use Modules\Content\Schemas\Module;
use Modules\Content\Transfer\ContentTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;

/**
 * Content module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Content).
 */
class ExportContentDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ContentTransfer::class);
    }
}
