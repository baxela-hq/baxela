<?php

use Modules\Core\Support\Fx\OpenExchangeRatesProvider;

return [
    /*
    |--------------------------------------------------------------------------
    | FX rate auto-sync (launch-readiness Part E)
    |--------------------------------------------------------------------------
    |
    | Opt-in exchange-rate synchronization from an external provider.
    | Manual rates remain the default and always remain editable; a
    | failed sync never mutates rates.
    |
    | Credentials: the admin-entered key (Setting) takes precedence; this
    | env value is the single fallback (env access stays inside config,
    | review F7). The key travels only in the Authorization header —
    | never a URL — and must never appear in logs or status output.
    |
    */

    'default' => env('FX_PROVIDER', 'openexchangerates'),

    'fallback_api_key' => env('FX_API_KEY', ''),

    // The sync lock TTL must exceed the HTTP timeout plus processing;
    // the engine enforces lock.seconds >= 4 × http.timeout (review F14).
    'http' => [
        'timeout' => (int) env('FX_HTTP_TIMEOUT', 15),
    ],

    'lock' => [
        'seconds' => (int) env('FX_LOCK_SECONDS', 60),
    ],

    // Failed syncs retry at the next scheduler tick only after this
    // cooldown; success cadence is governed by the admin interval (F19).
    'retry' => [
        'cooldown_minutes' => (int) env('FX_RETRY_COOLDOWN_MINUTES', 30),
    ],

    // A snapshot older than this is rejected as stale (review F6).
    'max_rate_age_hours' => (int) env('FX_MAX_RATE_AGE_HOURS', 48),

    // A snapshot timestamped more than this far in the future is rejected.
    'future_tolerance_minutes' => (int) env('FX_FUTURE_TOLERANCE_MINUTES', 10),

    'providers' => [
        'openexchangerates' => [
            'class' => OpenExchangeRatesProvider::class,
            // Overridable so exchangerate.host-style endpoints drop in later.
            'base_url' => env('FX_OPENEXCHANGERATES_BASE_URL', 'https://openexchangerates.org/api'),
        ],
    ],
];
