<?php

use Modules\User\Transfer\UserTransfer;

return [
    'name' => 'User',

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => UserTransfer::class,
    ],
];
