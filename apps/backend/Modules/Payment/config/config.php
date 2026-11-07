<?php

use Modules\Payment\Transfer\PaymentTransfer;

return [
    'name' => 'Payment',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => PaymentTransfer::class,
    ],
];
