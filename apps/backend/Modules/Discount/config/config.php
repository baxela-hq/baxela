<?php

use Modules\Discount\Transfer\DiscountTransfer;

return [
    'name' => 'Discount',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => DiscountTransfer::class,
    ],
];
