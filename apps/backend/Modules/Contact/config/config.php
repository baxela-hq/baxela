<?php

use Modules\Contact\Transfer\ContactTransfer;

return [
    'name' => 'Contact',

    // Per-IP submissions per minute on the public contact endpoint.
    'rate_limit' => [
        'submit' => env('CONTACT_SUBMIT_RATE_LIMIT', 5),

        // Newsletter sign-ups — more generous: idempotent per email anyway.
        'subscribe' => env('CONTACT_SUBSCRIBE_RATE_LIMIT', 10),
    ],

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see Modules/Core/.../Transfer/ModuleTransferInterface).
    'transfer' => [
        'class' => ContactTransfer::class,
    ],
];
