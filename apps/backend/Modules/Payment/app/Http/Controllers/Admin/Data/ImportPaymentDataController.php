<?php

namespace Modules\Payment\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferImportController;
use Modules\Payment\Schemas\Module;
use Modules\Payment\Transfer\PaymentTransfer;

/**
 * Payment module's own import endpoint — replays an uploaded transfer
 * file through PaymentTransfer; dry runs validate without writing.
 */
class ImportPaymentDataController extends ModuleTransferImportController
{
    protected function moduleName(): string
    {
        return Module::NAME_LOWER;
    }

    protected function transfer(): ModuleTransferInterface
    {
        return app(PaymentTransfer::class);
    }
}
