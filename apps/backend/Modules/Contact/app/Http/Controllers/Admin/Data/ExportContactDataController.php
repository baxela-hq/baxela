<?php

namespace Modules\Contact\Http\Controllers\Admin\Data;

use Modules\Contact\Schemas\Module;
use Modules\Contact\Transfer\ContactTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;

/**
 * Contact module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with Contact).
 */
class ExportContactDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ContactTransfer::class);
    }
}
