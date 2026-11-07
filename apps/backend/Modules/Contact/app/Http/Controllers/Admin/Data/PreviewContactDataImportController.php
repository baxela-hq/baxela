<?php

namespace Modules\Contact\Http\Controllers\Admin\Data;

use Modules\Contact\Schemas\Module;
use Modules\Contact\Transfer\ContactTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;

/**
 * Contact module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewContactDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(ContactTransfer::class);
    }
}
