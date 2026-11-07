<?php

namespace Modules\Setting\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Setting\Schemas\Module;
use Modules\Setting\Transfer\SettingTransfer;

/**
 * Setting module's own import endpoint — replays an uploaded transfer
 * file through SettingTransfer; dry runs validate without writing.
 */
class ImportSettingDataController extends ModuleTransferImportController
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
