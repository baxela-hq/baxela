<?php

namespace Modules\Cart\Http\Controllers\Admin\Data;

use Modules\Cart\Schemas\Module;
use Modules\Cart\Transfer\CartTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;

/**
 * Cart module's own import endpoint — replays an uploaded transfer
 * file through CartTransfer; dry runs validate without writing.
 */
class ImportCartDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(CartTransfer::class);
    }
}
