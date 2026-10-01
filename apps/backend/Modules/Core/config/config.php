<?php

return [
    'name' => 'Core',
    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable request/response logging globally.
    | Keep this disabled unless debugging: event payloads can carry live
    | credentials (recognized secrets are redacted, but any logging of
    | authentication flows is a leak surface).
    |
    */
    'log_requests' => env('CORE_LOG_REQUESTS', false),
];
