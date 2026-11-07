<?php

namespace Modules\Support\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Support\Schemas\Module;
use Modules\Support\Transfer\SupportTransfer;

/**
 * Support module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewSupportDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(SupportTransfer::class);
    }
}
