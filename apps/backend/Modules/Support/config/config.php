<?php

return [
    'name' => 'Support',

    // Per-user ticket writes per minute on the customer endpoints.
    'rate_limit' => [
        'create' => env('SUPPORT_TICKET_CREATE_RATE_LIMIT', 10),

        'reply' => env('SUPPORT_TICKET_REPLY_RATE_LIMIT', 30),
    ],
];
