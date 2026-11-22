<?php

use Modules\Core\Transfer\CoreTransfer;

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

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies (S.3)
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs/CIDRs allowed to send forwarding headers
    | (X-Forwarded-For / X-Forwarded-Proto only — see TrustProxies
    | middleware). Empty trusts nobody: forwarded headers are ignored
    | and $request->secure() reflects only real TLS. Deployments set
    | this to the compose `proxy` network's subnet — never `*`, which
    | on the shared compose network would trust every container peer.
    |
    */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    // Data-transfer standard registration — picked up by the Transfer
    // module's registry (see docs/transfer.md).
    'transfer' => [
        'class' => CoreTransfer::class,

        // Import-lease protocol (postgres-migration §3.1): how long an
        // import waits for a contended destination-module lease before
        // failing with `import_in_progress`, and how long an acquired
        // lease lives between write-unit renewals (DB clock).
        'lock_timeout' => (int) env('TRANSFER_LOCK_TIMEOUT', 10),
        'lock_lease_ttl' => (int) env('TRANSFER_LOCK_LEASE_TTL', 60),
    ],
];
