<?php

return [
    'name' => 'Contact',

    // Per-IP submissions per minute on the public contact endpoint.
    'rate_limit' => [
        'submit' => env('CONTACT_SUBMIT_RATE_LIMIT', 5),

        // Newsletter sign-ups — more generous: idempotent per email anyway.
        'subscribe' => env('CONTACT_SUBSCRIBE_RATE_LIMIT', 10),
    ],
];
