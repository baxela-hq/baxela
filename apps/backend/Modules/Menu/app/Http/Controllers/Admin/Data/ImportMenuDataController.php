<?php

namespace Modules\Menu\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Menu\Schemas\Module;
use Modules\Menu\Transfer\MenuTransfer;

/**
 * Menu module's own import endpoint — replays an uploaded transfer
 * file through MenuTransfer; dry runs validate without writing.
 */
class ImportMenuDataController extends ModuleTransferImportController
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
