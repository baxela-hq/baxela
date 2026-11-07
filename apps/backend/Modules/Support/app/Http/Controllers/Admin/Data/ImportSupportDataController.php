<?php

namespace Modules\Support\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Support\Schemas\Module;
use Modules\Support\Transfer\SupportTransfer;

/**
 * Support module's own import endpoint — replays an uploaded transfer
 * file through SupportTransfer; dry runs validate without writing.
 */
class ImportSupportDataController extends ModuleTransferImportController
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
