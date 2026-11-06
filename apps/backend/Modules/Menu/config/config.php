<?php

use Modules\Menu\Transfer\MenuTransfer;

return [
    'name' => 'Menu',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => MenuTransfer::class,
    ],
];
