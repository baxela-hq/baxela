<?php

return [
    // Delivery retry policy (gap-C2 D9): first attempt immediate, then the
    // four backoff steps; 5 attempts total. 3xx and 4xx (except 408/429)
    // are permanent failures; 408/429/5xx/transport are retryable.
    'max_attempts' => 5,
    'backoff_seconds' => [30, 120, 600, 3600],

    // Auto-disable an endpoint after this many consecutive failures
    // (terminal-state order, serialized by the endpoint lock — gap-C2 D10).
    'failure_threshold' => 10,

    // Delivery claim lease; expired claims are reclaimable by the sweep.
    'claim_lease_seconds' => 600,

    // SSRF guard (gap-C2 D6): https-only outside local environments,
    // IP pinning via CURLOPT_RESOLVE, no redirects, capped response body.
    'https_only' => true,
    'allowed_ports' => [443],
    'max_response_bytes' => 65536,
    'connect_timeout_seconds' => 10,
    'timeout_seconds' => 15,

    // Retention (gap-C2 D12): terminal deliveries and fully-terminal
    // outbox events older than this are pruned.
    'retention_days' => 90,
];
