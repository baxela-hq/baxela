<?php

namespace Modules\Tax\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Tax\Schemas\Module;
use Modules\Tax\Transfer\TaxTransfer;

/**
 * Tax module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewTaxDataImportController extends ModuleTransferPreviewController
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
