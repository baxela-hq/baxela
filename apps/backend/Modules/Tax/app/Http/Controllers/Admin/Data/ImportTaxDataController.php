<?php

namespace Modules\Tax\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Tax\Schemas\Module;
use Modules\Tax\Transfer\TaxTransfer;

/**
 * Tax module's own import endpoint — replays an uploaded transfer file
 * through TaxTransfer; dry runs validate without writing.
 */
class ImportTaxDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(TaxTransfer::class);
    }
}
