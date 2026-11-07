<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue visibility (gap-B3)
    |--------------------------------------------------------------------------
    |
    | Operational settings for the failed-jobs admin surface and the scheduled
    | queue-depth monitor. Accessed under the `core.queue.*` prefix (module
    | configs are merged with the module name as prefix).
    |
    */

    'failed_jobs' => [
        /*
        | Secret-ish payload keys masked before a failed job's payload is shown
        | to an admin. Matching is a case-insensitive substring match on the key
        | name — it is a safety net, not a guarantee: the serialized job command
        | is never displayed at all (gap-B3 D3).
        */
        'redacted_payload_keys' => [
            'password',
            'passwd',
            'secret',
            'token',
            'authorization',
            'bearer',
            'api_key',
            'apikey',
            'credentials',
            'private_key',
            'access_key',
        ],

        'exception_excerpt_length' => 300,

        'exception_search_min_length' => 3,

        /*
        | Retention for the nightly `queue:prune-failed` schedule (gap-B3 D7).
        | Pruning is permanent deletion; keeping the table pruned is also what
        | keeps the exception substring search cheap.
        */
        'retention_days' => 30,
    ],

    /*
    | Scheduled queue-depth monitor (`core:queue-check-backlog`, gap-B3 D6).
    | Depth, not throughput: it alerts when the configured queue depth exceeds
    | the threshold — a healthy-but-large queue is above threshold by design.
    */
    'backlog' => [
        'threshold' => 100,

        /*
        | Monitored targets as [connection, queue] pairs; `null` resolves to the
        | default connection / the connection's default queue at runtime. Add
        | named queues here when they are introduced — never in code.
        */
        'targets' => [
            ['connection' => null, 'queue' => null],
        ],

        /*
        | The alert-state key TTL: dedup + a maximum suppression period, not a
        | strict cooldown. A continuously-busy queue re-alerts when the key
        | expires; a recovered queue clears state immediately.
        */
        'state_ttl_hours' => 24,
    ],
];
