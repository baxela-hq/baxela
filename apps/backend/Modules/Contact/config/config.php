<?php

return [
    'name' => 'Contact',

    // Per-IP submissions per minute on the public contact endpoint.
    'rate_limit' => [
        'submit' => env('CONTACT_SUBMIT_RATE_LIMIT', 5),
    ],
];
