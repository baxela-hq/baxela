<?php

namespace Modules\Payment\Http\Controllers\Admin\Data;

use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Core\Http\Controllers\Transfer\ModuleTransferPreviewController;
use Modules\Payment\Schemas\Module;
use Modules\Payment\Transfer\PaymentTransfer;

/**
 * Payment module's own import-preview endpoint (structure check of an
 * uploaded transfer file).
 */
class PreviewPaymentDataImportController extends ModuleTransferPreviewController
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
