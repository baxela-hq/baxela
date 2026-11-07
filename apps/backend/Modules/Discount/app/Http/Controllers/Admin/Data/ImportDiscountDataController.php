<?php

namespace Modules\Discount\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Discount\Schemas\Module;
use Modules\Discount\Transfer\DiscountTransfer;

/**
 * Discount module's own import endpoint — replays an uploaded transfer
 * file through DiscountTransfer; dry runs validate without writing.
 */
class ImportDiscountDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(DiscountTransfer::class);
    }
}
