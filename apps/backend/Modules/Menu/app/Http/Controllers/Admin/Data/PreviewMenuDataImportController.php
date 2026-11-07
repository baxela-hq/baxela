<?php

namespace Modules\Menu\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Menu\Schemas\Module;
use Modules\Menu\Transfer\MenuTransfer;

/**
 * Menu module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewMenuDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(MenuTransfer::class);
    }
}
