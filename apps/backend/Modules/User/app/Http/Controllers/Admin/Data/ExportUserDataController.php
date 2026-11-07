<?php

namespace Modules\User\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferExportController;
use Modules\User\Schemas\Module;
use Modules\User\Transfer\UserTransfer;

/**
 * User module's own data export endpoint — the module-bound subclass
 * of the shared Core file plane (microservice-ready: travels with User).
 */
class ExportUserDataController extends ModuleTransferExportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(UserTransfer::class);
    }
}
