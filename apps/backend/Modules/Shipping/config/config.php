<?php

use Modules\Shipping\Transfer\ShippingTransfer;

return [
    'name' => 'Shipping',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => ShippingTransfer::class,
    ],
];
