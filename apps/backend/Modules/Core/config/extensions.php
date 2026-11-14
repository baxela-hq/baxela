<?php

// Marketplace integration (P5.3): the backend proxy that feeds the
// admin "update available" badge. The URL stays empty until the
// registry is deployed publicly — an empty value turns the whole
// feature off (marketplace_unconfigured, no outbound HTTP). Env access
// stays inside this file, never in action code.
return [
    'marketplace_url' => env('MARKETPLACE_URL', ''),

    'http' => [
        // Total request timeout; the badge is advisory — no retries,
        // no long waits. Connection establishment gets 3s of it.
        'timeout' => env('MARKETPLACE_HTTP_TIMEOUT', 5),
    ],
];
