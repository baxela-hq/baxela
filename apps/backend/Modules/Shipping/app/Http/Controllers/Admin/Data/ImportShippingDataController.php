<?php

namespace Modules\Shipping\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Shipping\Schemas\Module;
use Modules\Shipping\Transfer\ShippingTransfer;

/**
 * Shipping module's own import endpoint — replays an uploaded transfer
 * file through ShippingTransfer; dry runs validate without writing.
 */
class ImportShippingDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ShippingTransfer::class);
    }
}
