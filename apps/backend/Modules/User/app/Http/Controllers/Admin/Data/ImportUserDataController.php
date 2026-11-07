<?php

namespace Modules\User\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\User\Schemas\Module;
use Modules\User\Transfer\UserTransfer;

/**
 * User module's own import endpoint — replays an uploaded transfer
 * file through UserTransfer; dry runs validate without writing.
 */
class ImportUserDataController extends ModuleTransferImportController
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
