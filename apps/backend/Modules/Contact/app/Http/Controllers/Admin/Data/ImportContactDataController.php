<?php

namespace Modules\Contact\Http\Controllers\Admin\Data;

use Modules\Contact\Schemas\Module;
use Modules\Contact\Transfer\ContactTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;

/**
 * Contact module's own import endpoint — replays an uploaded transfer
 * file through ContactTransfer; dry runs validate without writing.
 */
class ImportContactDataController extends ModuleTransferImportController
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
