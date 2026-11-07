<?php

namespace Modules\Shipping\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Shipping\Schemas\Module;
use Modules\Shipping\Transfer\ShippingTransfer;

/**
 * Shipping module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewShippingDataImportController extends ModuleTransferPreviewController
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
