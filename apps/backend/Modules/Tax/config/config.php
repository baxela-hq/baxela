<?php

use Modules\Tax\Transfer\TaxTransfer;

return [
    'name' => 'Tax',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => TaxTransfer::class,
    ],
];
