<?php

/*
 * Meilisearch integration (alpha.3). Env access stays inside this file,
 * never in action code (house convention). An empty `host` turns the
 * whole engine off — the search endpoint falls back to the SQL filter
 * path and the platform behaves exactly as before the feature.
 */
return [
    'host' => env('MEILISEARCH_HOST', ''),

    // Same value as MEILI_MASTER_KEY in the root .env; never exposed to
    // the storefront (the browser only ever talks to the Laravel API).
    'master_key' => env('MEILISEARCH_MASTER_KEY', env('MEILI_MASTER_KEY', '')),

    // Hard upper bound on a search request through the adapter (v16 F4):
    // enforced at the transport layer, no retries, no same-uid re-issue.
    'read_timeout' => (int) env('MEILISEARCH_READ_TIMEOUT', 5),

    // Hard bound for submit/poll/health requests through the adapter.
    'request_timeout' => (int) env('MEILISEARCH_REQUEST_TIMEOUT', 10),

    // Terminal-status wait budget per submitted task (the writer lease is
    // 3x this — renewal keeps ownership for the whole await).
    'task_await' => (int) env('MEILISEARCH_TASK_AWAIT', 60),

    // Base uid for generation indexes: catalog_products_g{generation}.
    'uid_base' => env('MEILISEARCH_UID_BASE', 'catalog_products'),

    // Retired-index deletion grace, in minutes, counted from the pointer
    // flip; must exceed the maximum search request lifetime by design.
    'drain_grace_minutes' => (int) env('MEILISEARCH_DRAIN_GRACE', 5),

    // Writer lease TTL (seconds); renewed while awaiting terminal status.
    'lease_ttl' => (int) env('MEILISEARCH_LEASE_TTL', 180),

    // Mutation-admission drain budget for the cutover barrier (seconds).
    'drain_timeout' => (int) env('MEILISEARCH_DRAIN_TIMEOUT', 30),

    // Worker-heartbeat staleness threshold (§3.2.2): the pre-filter for
    // dead-admission reclamation and writer-lease claims. Never proof of
    // death by itself — /proc incarnation proof is also required.
    'worker_stale_after' => (int) env('MEILISEARCH_WORKER_STALE_AFTER', 60),

    // Reconciler cadence (§3.4): the drift-repair interval. The
    // convergence SLO is ~2x this value (assumptions documented in the
    // postgres-migration plan), with the nightly reindex as backstop.
    'reconcile_interval' => (int) env('MEILISEARCH_RECONCILE_INTERVAL', 300),

    // Reconciler per-pass batch (§3.4 F2 fairness): oldest-lag-first, at
    // least min(all, K) products per pass — no product starves.
    'reconcile_batch' => (int) env('MEILISEARCH_RECONCILE_BATCH', 500),

    // Consecutive failed reconciler passes before alerting (§3.4).
    'reconcile_alert_after' => (int) env('MEILISEARCH_RECONCILE_ALERT_AFTER', 3),
];
