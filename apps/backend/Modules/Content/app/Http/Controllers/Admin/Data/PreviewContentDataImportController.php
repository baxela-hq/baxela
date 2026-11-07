<?php

namespace Modules\Content\Http\Controllers\Admin\Data;

use Modules\Content\Schemas\Module;
use Modules\Content\Transfer\ContentTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;

/**
 * Content module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewContentDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ContentTransfer::class);
    }
}
