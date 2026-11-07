<?php

namespace Modules\Core\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Core\Schemas\Module;
use Modules\Core\Transfer\CoreTransfer;

/**
 * Core module's own import-preview endpoint.
 */
class PreviewCoreDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(CoreTransfer::class);
    }
}
