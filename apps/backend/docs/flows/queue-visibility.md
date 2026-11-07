# Queue visibility — failed jobs, alerts, backlog monitor

> Operational flow for Baxela's queue-visibility surface (gap-B3,
> `docs/plans/gap-b3-queue-visibility.md`). Audience: operators on-call and
> developers adding queued work.

## What this is (and is not)

The failed-jobs admin page (**Admin → Failed Jobs**, `core.admin.failed-jobs.*`
permissions) shows *recorded failures* — it is visibility, not worker
management. It cannot start, stop, or restart `queue:work`/Horizon processes;
worker health stays the compose stack's job (`restart: unless-stopped`,
healthchecks, the A.3 health endpoint). Laravel's queue system remains the
implementation; the Core module only wraps operations on recorded failures.

## Lifecycle of a failed job

```
queued job throws
  → Laravel retries per the job's $tries / retry_after
  → final failure: row inserted into failed_jobs (database-uuids failer,
    independent of the queue driver — works under database AND redis queues)
  → Illuminate\Queue\Events\JobFailed fires
      → NotifyAdminJobFailedListener (synchronous, in the worker process)
      → admin alert on database + email + webpush + telegram channels
      → exception excerpt lands ONLY in the database inbox (admin UI);
        external channels carry identifiers only (job, queue, uuid, time)
  → operator opens Admin → Failed Jobs
      → Retry: re-queues the job for another attempt (row is removed)
      → Forget: permanently deletes the record
  → nightly queue:prune-failed (retention: core.queue.failed_jobs.retention_days,
    default 30 days) deletes aged rows — permanent
```

## Retry is not recovery — idempotency expectations for job authors

**Retry means "re-queue this failed job for another execution attempt"** — it
does not mean the job recovered, and it very often *can succeed while
repeating side effects* (a payment job that charged before timing out on the
recording step will charge again when retried). The admin UI carries this
warning on every retry confirmation.

For job authors, therefore:

- Make queued work **idempotent where possible** — especially anything touching
  payments, orders, inventory, or external services. Check-then-act with a
  persistent marker (`exists? → skip`) beats blind re-execution.
- Prefer failure *before* irreversible side effects over failure *after* them.
- Do not assume a retried job runs in the same process, locale, or against the
  same external state as the original attempt.

## Backlog monitor (depth, not throughput)

`core:queue-check-backlog` is scheduled every five minutes with
`withoutOverlapping()`. Per configured target (`core.queue.backlog.targets`,
default: the default connection's default queue):

- **depth > threshold** (`core.queue.backlog.threshold`, default 100): a fresh
  crossing fires `QueueBacklogDetectedEvent` → `NotifyAdminQueueBacklogListener`
  → admin alert. While the depth stays above, re-checks are suppressed by an
  atomic cache-state key; the key's TTL (`backlog.state_ttl_hours`, default 24)
  makes suppression a *dedup + maximum suppression period*, not a cooldown —
  a continuously-busy queue re-alerts when it expires.
- **depth ≤ threshold**: the state key is cleared, so the next crossing
  re-alerts.

This is **depth monitoring, not throughput monitoring** — a healthy-but-large
queue is above threshold by design; queue-age/latency detection is future work.
Cache failures fail open: dedup is best-effort, alerts still go out.

## Alert delivery is best-effort

The alert listeners are synchronous (an alert about a broken queue cannot ride
the queue) and fully exception-isolated — a notification failure is logged
(identifiers only) and never interferes with Laravel's failure handling.
Delivery depends on the configured channels (SMTP, Telegram bot, VAPID keys);
the **database inbox is the primary in-app alert**. For guaranteed-delivery
operational alerting, integrate external monitoring instead of extending this
surface.

## Shared hosting caveat

Shared hosting runs a **sync queue** (no workers, no Redis): listeners execute
inside the HTTP request, so few or no failed rows accumulate there. The admin
page ships unchanged so the experience is identical on every deploy target.

## Ops runbook (quick reference)

```sh
# CLI fallback (same operations the admin page performs)
php artisan queue:failed            # list
php artisan queue:retry {uuid}      # re-queue one
php artisan queue:forget {uuid}     # delete one
php artisan queue:flush             # delete all

# Schedules (prod compose: the `scheduler` service must be running)
docker compose -f docker-compose.prod.yml exec scheduler php artisan schedule:list
# expect: core:queue-check-backlog (*/5, without-overlapping)
#         queue:prune-failed --hours=720 (daily)
```

Related: `docs/LOGGING.md` §Failed jobs · `docs/plans/gap-b3-queue-visibility.md`
· `infrastructure/docker/production/README.md` (Day-2 operations).
