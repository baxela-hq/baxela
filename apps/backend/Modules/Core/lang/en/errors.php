<?php

return [
    '401' => 'Unauthorized',
    '403' => 'Forbidden',
    '404' => 'Resource not found',
    '422' => 'Validation failed',
    '429' => 'Too many requests. Please try again later.',
    '500' => 'Something went wrong',
    'internal_server_error' => 'Something went wrong',

    // Failed-jobs admin surface (gap-B3) — nested keys resolve via
    // ExceptionHelper (core.failed_jobs.* → core::errors.failed_jobs.*).
    'failed_jobs' => [
        'not_found' => 'The failed job was not found',
        'search_too_short' => 'The exception search must be at least :min characters',
    ],

    // FX rate auto-sync (Part E) — sanitized, stable codes.
    'fx_no_default_currency' => 'No default currency is configured.',
    'base_mismatch' => 'The provider returned rates for a different base currency. Rates were not modified.',
    'stale_rates' => 'The provider returned stale rates. Rates were not modified.',
    'invalid_timestamp' => 'The provider returned an invalid rate timestamp. Rates were not modified.',
    'missing_api_key' => 'No FX provider API key is configured. Rates were not modified.',
    'sync_failed' => 'The FX sync failed. Rates were not modified.',
    'fx' => [
        'sync_in_progress' => 'Another FX sync is already running.',
        'provider_auth_failed' => 'The FX provider rejected the API key.',
        'provider_forbidden' => 'The FX provider plan does not allow this request.',
        'provider_http_error' => 'The FX provider request failed.',
        'provider_timeout' => 'The FX provider did not respond in time.',
        'invalid_provider_document' => 'The FX provider returned an unreadable response.',
    ],
];
