<?php

return [
    'name' => 'Auth',

    // Per-IP attempts per minute on the public auth endpoints (brute-force protection).
    'rate_limit' => [
        'sign_in' => env('AUTH_SIGN_IN_RATE_LIMIT', 5),
        'sign_up' => env('AUTH_SIGN_UP_RATE_LIMIT', 5),
        'otp_request' => env('AUTH_OTP_REQUEST_RATE_LIMIT', 3),
        'otp_verify' => env('AUTH_OTP_VERIFY_RATE_LIMIT', 5),
    ],

    // Sanctum personal access token lifetimes, in days. `days` applies when
    // remember-me is requested (or the field is omitted); `short_days` when a
    // client explicitly signs in without remembering the device. Tokens are
    // stored client-side (localStorage) and cannot be revoked remotely, so
    // the remembered lifetime bounds how long a stolen token stays useful.
    'token_ttl' => [
        'days' => env('AUTH_TOKEN_TTL_DAYS', 7),
        'short_days' => env('AUTH_TOKEN_SHORT_TTL_DAYS', 1),
    ],
];
