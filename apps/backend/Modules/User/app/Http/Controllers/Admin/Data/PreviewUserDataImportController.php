<?php

namespace Modules\User\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\User\Schemas\Module;
use Modules\User\Transfer\UserTransfer;

/**
 * User module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewUserDataImportController extends ModuleTransferPreviewController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(UserTransfer::class);
    }
}
