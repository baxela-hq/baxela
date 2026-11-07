<?php

namespace Modules\Setting\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Setting\Schemas\Module;
use Modules\Setting\Transfer\SettingTransfer;

/**
 * Setting module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewSettingDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(SettingTransfer::class);
    }
}
