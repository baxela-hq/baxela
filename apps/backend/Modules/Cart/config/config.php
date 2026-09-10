<?php

return [
    'name' => 'Cart',

    // Per-token attempts per minute on the public guest-cart endpoints
    // (falls back to the client IP when no token is presented).
    'rate_limit' => [
        'public' => env('CART_PUBLIC_RATE_LIMIT', 60),
    ],
];
