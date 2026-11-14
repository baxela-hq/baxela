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

    // S.4 account lockout: after `max_attempts` failed sign-in attempts the
    // account refuses every attempt (even correct passwords) for
    // `lockout_minutes`. The inactivity-reset window of the failure counter
    // is this same value (D2i) — no separate key on purpose.
    'lockout' => [
        'max_attempts' => env('AUTH_LOCKOUT_MAX_ATTEMPTS', 5),
        'lockout_minutes' => env('AUTH_LOCKOUT_MINUTES', 15),
    ],

    // S.1 two-factor authentication. `enabled=false` removes the sign-in
    // challenge and the forced super-admin enrollment ENTIRELY — an
    // enrolled account signs in with password alone (enrollment data is
    // preserved, so re-enabling restores every factor). Intended for
    // local development; production keeps the roadmap policy "admin
    // access is two-factor".
    'two_factor' => [
        'enabled' => env('AUTH_TWO_FACTOR_ENABLED', true),
        // S.1 D9: force super-admins through enrollment at sign-in.
        'force_super_admin' => env('AUTH_TWO_FACTOR_FORCE_SUPER_ADMIN', true),
    ],

    // Google sign-in (customer-only). The storefront must not render the
    // button unless its own flag is on, but THIS flag is the security
    // control: when disabled, both the credential and nonce endpoints
    // refuse with the same disabled-feature error. `nonce_ttl` bounds how
    // long an issued nonce may be consumed (seconds).
    'google' => [
        'enabled' => env('AUTH_GOOGLE_ENABLED', false),
        'client_id' => env('AUTH_GOOGLE_CLIENT_ID'),
        'nonce_ttl' => 300,
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
