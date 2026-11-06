<?php

use Modules\Setting\Transfer\SettingTransfer;

return [
    'name' => 'Setting',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => SettingTransfer::class,
    ],
];
