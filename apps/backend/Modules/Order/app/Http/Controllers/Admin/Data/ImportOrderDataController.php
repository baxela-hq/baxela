<?php

namespace Modules\Order\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Order\Schemas\Module;
use Modules\Order\Transfer\OrderTransfer;

/**
 * Order module's own import endpoint — replays an uploaded transfer
 * file through OrderTransfer; dry runs validate without writing.
 */
class ImportOrderDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(OrderTransfer::class);
    }
}
