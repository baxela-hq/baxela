<?php

namespace Modules\Content\Http\Controllers\Admin\Data;

use Modules\Content\Schemas\Module;
use Modules\Content\Transfer\ContentTransfer;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;

/**
 * Content module's own import endpoint — replays an uploaded transfer
 * file through ContentTransfer; dry runs validate without writing.
 */
class ImportContentDataController extends ModuleTransferImportController
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
