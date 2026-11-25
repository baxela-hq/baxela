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

    /*
    |--------------------------------------------------------------------------
    | Tenancy (multi-tenancy §3.3)
    |--------------------------------------------------------------------------
    |
    | An unregistered host resolves to the default tenant ONLY when it
    | matches the fallback list. The default covers loopback names so
    | local development needs no tenant setup; production ships an EMPTY
    | list, so an unknown domain 404s instead of falling back.
    |
    */
    'tenancy' => [
        'fallback_hosts' => env('TENANCY_FALLBACK_HOSTS', 'localhost,127.0.0.1'),
        'fallback_tenant' => 'default',
        // Admin API hosts where host-based resolution is skipped and the
        // store comes from X-Tenant-Id / membership selection instead.
        'central_hosts' => env('TENANCY_CENTRAL_HOSTS', ''),

        // Routes allowed to carry the tenant-neutral `onboarding`
        // audience (§3.1). They skip tenant resolution entirely; anything
        // else using that audience fails the route-audience test.
        'onboarding_routes' => [],

        // Platform-level routes that carry no audience segment (or an
        // empty one). Identification treats them like `public`.
        'platform_exceptions' => [
            'api.notification.webpush.vapid-public-key',
            'api.notification.telegram.bot-info',
            'api.notification.',
        ],
    ],

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
