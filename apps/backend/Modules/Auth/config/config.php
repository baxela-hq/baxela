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
];
