<?php

namespace Modules\Cart\Http\Controllers\Admin\Data;

use Modules\Cart\Schemas\Module;
use Modules\Cart\Transfer\CartTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;

/**
 * Cart module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewCartDataImportController extends ModuleTransferPreviewController
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
