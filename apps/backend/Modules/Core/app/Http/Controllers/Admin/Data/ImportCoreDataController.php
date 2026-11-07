<?php

namespace Modules\Core\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Core\Schemas\Module;
use Modules\Core\Transfer\CoreTransfer;

/**
 * Core module's own import endpoint — replays reference data
 * (upsert by code; the default flags demote every other row).
 */
class ImportCoreDataController extends ModuleTransferImportController
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
