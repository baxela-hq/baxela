<?php

namespace Modules\Discount\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Discount\Schemas\Module;
use Modules\Discount\Transfer\DiscountTransfer;

/**
 * Discount module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewDiscountDataImportController extends ModuleTransferPreviewController
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
