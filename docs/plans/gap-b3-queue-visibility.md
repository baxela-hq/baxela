# Plan gap-B3 — Queue visibility: failed jobs, alerts, backlog monitor

> **Purpose of this document:** Elaborate the gap (what queue visibility *is*, why the
> platform needs it) and serve as the execution plan for Phase B task **B.3** of
> `docs/PLATFORM_GAPS_ROADMAP.md`. §1 stands alone — read it to understand the domain;
> the rest is build order.
>
> **Created:** 2026-11-07 · **Status:** **approved 2026-11-07** (final review — 9.5/10,
> "implementation-ready after minor corrections"; all seven folded in with framework
> verification, see §9) — **implementation not started** · **Size:** **1.5–2 developer
> days** (the roadmap's "half a day" calibrated up across three review rounds: the
> permission split, alert deduplication, payload handling, six-locale templates, and
> real worker-integration tests).
>
> **How to use:** Build in the six steps of §4, in order — each step is testable on its
> own. Re-verify any fact marked ⚠ against the codebase before relying on it. Heavy
> commands `nice -n 10`; scope test runs to the touched modules. Conventional commits;
> never commit unless asked. **The final review approves implementation** — with a
> scope guard: no Horizon, dashboards, queue metrics, or worker management creep in
> during implementation.

---

## 1. Primer — what "queue visibility" means and why Baxela needs it

### 1.1 The blind spot

Baxela's queue is not decorative: every customer-facing email, Telegram message,
web-push and in-app notification is a queued listener (20 of them today, all in the
Notification module). When one throws — SMTP down, Telegram API erroring, a bug in a
listener — Laravel moves the job to the `failed_jobs` table and the platform moves on.
Today nobody sees that table unless they SSH in and run `php artisan queue:failed` by
hand; `docs/LOGGING.md` documents exactly that manual ritual and names automated
alerting as a "tracked follow-up". The health endpoint (A.3) reports *aggregate*
failed/pending counts to uptime monitors, but a single failing notification job — the
common case — never crosses a monitor threshold. The operator learns about a broken
email channel from an angry merchant, not from the platform.

### 1.2 What this phase delivers

Four small capabilities, one surface:

1. **See** — an admin page listing recorded failed jobs (uuid, job, connection, queue,
   failed-at, exception excerpt) with a full detail view (complete exception, payload).
2. **Fix** — retry a failed job (re-queue for another attempt) or forget it (permanent
   deletion), both confirmed and separately permissioned.
3. **Hear** — a synchronous, best-effort admin alert when a job finally fails, plus a
   backlog alert when the configured queue **depth** exceeds its operational threshold
   (depth, not throughput — a healthy-but-large queue is above threshold by design),
   with deduplication so a stuck queue doesn't spam.
4. **Keep clean** — automatic pruning of old failed rows, so the table stays small
   enough that `%LIKE%` exception search is safe.

And a decision: **Horizon is consciously deferred** (see §3 D8). The minimal surface
works on every deploy target — dev compose (database queue), prod compose (redis
queue), shared hosting (sync queue) — where Horizon could only ever serve one of the
three. Nothing here precludes adding Horizon later.

### 1.3 Visibility ≠ processing

This page shows *recorded failures*; it does not manage workers. It cannot start,
stop, or restart `queue:work`/Horizon processes, and it must never be mistaken for a
worker-management UI. Worker health stays the compose stack's job (`restart:
unless-stopped`, healthchecks, the A.3 health endpoint).

### 1.4 Retry semantics — the honest version

**Retry means "re-queue this failed job for another execution attempt".** It does not
mean "recovered" — the job can fail again, and it very often *can succeed while
repeating side effects*. A job that charged a card before timing out on the recording
step, retried, charges again. The admin UI therefore carries a permanent warning on
retry (and on forget, which is irreversible deletion), and the platform's expectation
— documented for job authors — is that queued work is idempotent where possible,
especially anything touching payments, orders, inventory, or external services.

### 1.5 Worked example

```
10:00  SMTP provider has an outage
10:01  "Order confirmation" listener throws after 3 tries → failed_jobs row
10:01  JobFailed fires → synchronous alert → admin inbox + email ("job X failed
       on queue default — inspect in Admin → Failed Jobs", no exception text
       in external channels)
10:05  Admin opens the page: sees the job, reads the full exception in the
       detail view (provider DNS timeout), waits out the outage
10:40  Admin hits Retry → job re-queued → processes → email delivered
11:00  queue:check-backlog run: queue size below threshold → backlog alert
       state (if any) cleared
30 days later  nightly queue:prune-failed deletes the aged row (permanent)
```

## 2. Verified current state (2026-11-07)

**Everything this phase needs as *input* already exists; no queue machinery does.**

| Input needed | Exists | Evidence |
|---|---|---|
| `failed_jobs` table | ✅ | Framework migration `apps/backend/database/migrations/0001_01_01_000002_create_jobs_table.php:37` (uuid, connection, queue, payload, exception, failed_at); also `jobs`, `job_batches` |
| Failed driver, table-based | ✅ | `config/queue.php` → `database-uuids` (DB_CONNECTION mysql) — independent of the queue driver, so the surface works under database *and* redis queues |
| Queue driver topology | ✅ | Dev `.env(.example)`: `QUEUE_CONNECTION=database`; root `.env.production(.example):47-48`: **already** `QUEUE_CONNECTION=redis` + `CACHE_STORE=redis` — the roadmap's "switch prod to redis" is verification-only |
| Redis reachability | ✅ | `phpredis` compiled into both Docker images; `redis` service with healthcheck in both compose stacks |
| Scheduler execution | ✅ | Prod compose `scheduler` service runs `php artisan schedule:work`; registration hook: `CoreServiceProvider::registerCommandSchedules()` (currently a stub) — precedent `AuthServiceProvider:99-102`, `OrderServiceProvider:65-66` |
| Admin list pattern to mirror | ✅ | `Modules/Audit`: `routes/api/admin.php` (auth:sanctum + PermissionMiddleware), thin invokable controller → Action with Spatie QueryBuilder → Resource; permission auto-derived from route name (`PermissionMiddleware` + `SyncPermissionsAction`) |
| Admin alert pattern to mirror | ✅ | `Modules/Notification`: event → listener → `NotificationDispatcherInterface` → `NotificationMessage(code, audience: 'admin', recipients, data)` → channel matrix in `config/notifications.php`; recipients via `AdminRecipients`; reference listeners `NotifyAdminNewOrderListener`, `NotifyAdminPaymentFailedListener` |
| Queued work that can fail | ✅ | 20 `ShouldQueue` listeners (all Notification module, all on `default`, no named queues, no `onQueue`, no `failed()` handlers) — and Phase B.1 adds more queued email jobs, making this surface more valuable over time |
| Aggregate queue reporting | ✅ | `ShowStackHealthAction` (`Modules/Core`) already reads `config('queue.default')`, `Queue::size()`, `app('queue.failer')->count()` |
| Module config + commands + lang in Core | ✅ | `Modules/Core/config/` (auto-registered recursively by `CoreServiceProvider::registerConfig()`; keys prefixed `core.`), `app/Console/Commands/ErdGenerateCommand.php`, `routes/api/admin.php` (exact middleware group needed), `lang/en/errors.php` |
| Anything queue-visibility-ish beyond the above | ❌ | No Horizon/Telescope in composer, no `JobFailed`/`QueueBusy` listeners, no `queue:prune-failed` schedule, no failed-jobs admin surface anywhere |
| Shared-hosting constraint | ✅ | `infrastructure/sharedhosting/README.md:158` — "No horizon-style workers, no Redis"; sync queue means listeners run in-request, so few/no failed rows accumulate there (documented honestly in §5) |

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **Core module owns the admin surface, not the queue.** Endpoints, actions, schedules, and the backlog command live in Core (it already owns the health endpoint's queue reporting). Laravel's queue system stays the implementation — Core wraps *operations on recorded failures*, never queue internals. `FailedJob` is an Eloquent representation of a framework-managed table, configured explicitly because the table breaks the conventions: **`$table = 'failed_jobs'`, `$timestamps = false`, primary key `id`, `failed_at` cast to datetime — `failed_at` is not a Laravel-managed timestamp and must never be treated like `created_at`** — and **no relations, no domain logic, no behavior beyond read/retry/forget**. | Failed jobs are an infrastructure concern, not a business domain; a dedicated Queue module would be ceremony. Explicit model config avoids the silent pitfalls of a convention-breaking framework table (auto-timestamps on update, `updated_at` column not existing). |
| D2 | **Four separately-permissioned endpoints** on the AuditLog pattern: `GET api/v1/core/admin/failed-jobs` (list — QueryBuilder filters: `uuid` exact, `queue` exact, exception substring search with a **minimum 3-character length**; `%LIKE%` on the exception column is accepted *because* D7 keeps the table pruned — no full-text index v1), `GET .../failed-jobs/{uuid}` (show: full payload + exception), `POST .../failed-jobs/{uuid}/retry`, `DELETE .../failed-jobs/{uuid}` (forget). **Retry/forget mechanics, verified against the installed framework (Laravel 13.38):** the action pre-checks the row exists (the 404 path) *before* invoking anything; retry runs `Artisan::call('queue:retry', ['id' => [$uuid]])` — **the installed command takes an array argument** (`{id?*}`), exits 0 even when nothing matches, and on success itself removes the failed row (it dispatches `JobRetryRequested` and calls `failer->forget()`); forget calls the failer API directly — `app('queue.failer')->forget($uuid)` returns a bool — no console-output parsing, no exit-code guessing. Route names → auto-synced permissions **`core.admin.failed-jobs.{list,show,retry,delete}`** — route name `failed-jobs.show` (the Audit module's `logs.show` convention; permissions derive verbatim from route names, so no `show`→`view` mapping is invented) — **show never implies retry/delete**; non-privileged admins get 403 on those routes. List rows: uuid, job display name, connection, queue, failed-at, exception excerpt truncated **server-side to ≤300 chars**; full text only in the show endpoint. Pagination via the existing `Pagination::perPage()` clamp. | Separation of concerns from the Audit module costs nothing (permissions are auto-derived from route names) and is the review's required change: someone allowed to *look* at operational data must not automatically be able to *act* on it. Server-side truncation keeps list payloads small. Verified-not-assumed command semantics close the review's Artisan-argument and not-found-handling questions. |
| D3 | **Payload and exception handling — conservative by default.** The payload column is a JSON envelope; the resource: (a) **never unserializes anything**; (b) **never exposes the raw serialized `data.command` blob** — replaced by a placeholder (the envelope's `displayName` field already carries the job's class name ⚠ verify the envelope shape at implementation); (c) masks values under secret-ish keys in the remaining JSON via a case-insensitive denylist (password, token, secret, key, authorization, bearer…) before display; (d) treats unknown payload structures conservatively — when in doubt, hide; (e) renders everything as escaped text — the backend emits plain strings, the admin renders them as text, never HTML. **Channel-safe alert content (verified against the existing dispatcher):** `NotificationMessage::$data` is already channel-keyed — `DatabasePayloadBuilder` reads `data['database']`, `EmailPayloadBuilder` reads `data['email']`, `TelegramPayloadBuilder`/`WebPushPayloadBuilder` read their own key **with a fallback to `data['database']`**. Therefore: the `database` key may carry the exception excerpt; the `email` and `telegram` keys are **always passed explicitly with identifiers only** (job name, queue, uuid, failed-at, pointer to the admin page) — never relying on the fallback, which would leak exception text into external channels. No new notification abstraction. Exception text stays inside the admin UI and the admin's **database-channel inbox only**; logs carry identifiers only, never exception payloads or serialized commands. | Job payloads can embed credentials and serialized objects (review v1.2 §B); a denylist is a safety net, not a guarantee, so the serialized blob never renders at all. The channel-fallback discovery makes the per-channel rule enforceable by construction rather than by convention. |
| D4 | **Retry semantics explicit in the UI.** Retry = "re-queue for another execution attempt" — never "recovered". Both actions get **confirmation dialogs**: retry's dialog carries the side-effects warning (*retrying may repeat the job's side effects — verify the original operation before retrying jobs involving payments, orders, inventory, or external services*); forget's dialog states deletion is **permanent**. The idempotency expectation for job authors is documented (flow doc §Step 6). | The review's §5A payment double-charge scenario is realistic for an e-commerce platform; the UI must not make retry feel safe, and forget must not feel undoable. |
| D5 | **Failed-job alert: synchronous, isolated, bounded, best-effort.** `NotifyAdminJobFailedListener` in the Notification module listens to `Illuminate\Queue\Events\JobFailed`, **deliberately without `ShouldQueue`** — an alert about a broken queue must not depend on a working queue. The entire `handle()` is wrapped in try/catch → `Log::` (uuid/queue/job name only — never payload contents); a notification failure never rethrows, never interferes with Laravel's own failure handling, and cannot recurse into a second failure notification. External channels get **bounded connect/request timeouts** (⚠ verify current Email/Telegram client timeouts at implementation; set via config if missing) so a slow provider cannot stall failure processing — the **database inbox is the primary in-app alert**. Recipients via the existing `AdminRecipients` + config (admin audience only, never customers). New `NotificationCodeEnum` cases + config matrix entries; `LocaleTemplateEngine` templates ×6 locales with consistent ops terminology (failed job, queue, retry, exception, backlog); translations agent-drafted and flagged for owner review. Documented as **best-effort delivery** — guaranteed alerting later is external monitoring's job (Sentry already in composer), not this phase's. | The review's §2A: synchronous ≠ reliable, and §5B: don't overpromise. Fail-closed would be worse than fail-silent here — the listener's job is to *try* to tell someone and never to make the failure worse. |
| D6 | **Queue-depth monitor: one scheduled Core command owns detection AND recovery.** `core:queue-check-backlog` (registered via `CoreServiceProvider::registerCommands()`, scheduled every five minutes via `registerCommandSchedules()` **with `->withoutOverlapping()`**): reads `Queue::size()` for each configured target → **above threshold:** dedup via atomic `Cache::add(key)` — only a fresh crossing fires the new Core contract event `QueueBacklogDetectedEvent` (connection, queue, size, threshold) → Notification-module listener (same isolated shape as D5) alerts; **below threshold:** the command clears the cache state so the next crossing re-alerts. This is **depth monitoring, not throughput monitoring**: it alerts when the configured queue depth exceeds its operational threshold — a healthy queue that is simply large is above threshold by design, and queue-age/latency detection is a future enhancement (§7). **Cache failure = fail-open:** catch, log, still send the alert — dedup is best-effort and must never silently suppress everything. **State-TTL semantics documented honestly — the TTL is dedup + a maximum suppression period, not a strict cooldown:** a continuously-busy queue re-alerts when the key expires; a recovered queue clears state immediately on the next run; a stalled scheduler eventually allows another alert because the key expires. `Cache::add` dedups *crossings*; `withoutOverlapping()` (mutex in the cache) protects the detection-and-recovery *workflow* — together they close the A-adds/B-clears/A-alerts-again race the final review described; the single prod `scheduler` service makes overlap protection straightforward. **All targets, the threshold, and the state TTL come from config** (`Modules/Core/config/queue.php` — see §4 Step 2), never hardcoded; v1 configures the default connection's default queue, and adding named queues later is a config edit. | The v1.2 review's §A fix: `Illuminate\Queue\Events\QueueBusy` fires only on *crossing into* busy — there is no recovery event, so a `queue:monitor` + listener pair cannot clear state by itself. One command owning both directions is simpler and testable. The final review's concurrency point: atomic `Cache::add` alone doesn't make the whole check-then-act workflow atomic, so overlap protection is layered on top rather than assumed. |
| D7 | **Prune schedule + deployment verification.** `queue:prune-failed --hours={retention}` scheduled **daily** in Core, retention from the same Core config (default 30 days), documented as **permanent deletion**. A verification step confirms the prod compose `scheduler` service actually executes the schedules (a schedule registered in a provider is not a running scheduler): `docker compose -f docker-compose.prod.yml exec scheduler php artisan schedule:list` ⚠ and one manual observe-then-prune cycle. | The review's §C: pruning is destructive and must be labeled as such; the review's §7 order puts scheduler verification in its own step because schedule-in-provider ≠ schedule-running — a silent no-op here would eventually break the `%LIKE%`-is-cheap assumption of D2. |
| D8 | **Topology unchanged; scope hard boundary; Horizon deferred.** Dev stays `QUEUE_CONNECTION=database`; prod is already redis (verification only: confirm the compose `queue` service runs cleanly against redis — phpredis is in the image); failed driver stays `database-uuids` so the page works under **any** queue driver. Shared-hosting caveat documented: sync queue runs listeners in-request, so few/no failed rows accumulate there — the page ships anyway so the admin experience is identical on all targets. **This page is visibility, not worker management** (§1.3). **Horizon deferred** with date + rationale recorded in the roadmap: Redis-only (shared hosting excluded by design), a new daemon + protected dashboard for a single-queue workload of ~20 listeners, and re-addable later without rework. Explicit non-goals: worker management, metrics dashboards, observability expansion, full-text search, external monitoring integrations (see §7). | The user's surface decision (2026-11-07) plus the review's final scope guard: keep this a small, focused feature, not the start of an observability platform — health endpoint, logging, and Sentry already cover the rest. |

## 4. Work items

Order per the v1.2 review — backend before UI, schedules before notifications, tests
before the admin panel, docs last. Each step is independently testable.

### Step 1 — Core failed-jobs backend (no schedules yet)

- [ ] `Modules/Core/app/Models/FailedJob.php` — Eloquent over the framework
      `failed_jobs` table with the D1 configuration applied explicitly
      (`$table`, `$timestamps = false`, primary key `id`, datetime cast on
      `failed_at`); no relations, no domain logic; class docblock states it
      represents a framework-managed table.
- [ ] `Modules/Core/app/Schemas/FailedJob/FailedJobSchema.php` — column + filter
      constants (uuid, connection, queue, payload, exception, failed_at), mirroring
      `Modules/Audit/app/Schemas/AuditLog/AuditLogSchema.php` (shared
      `PkAndTimestampsTrait` doesn't apply — failed_at ≠ timestamps).
- [ ] Routes in `Modules/Core/routes/api/admin.php` (exact middleware group already
      present): the four routes of D2 with names `failed-jobs.list|show|retry|delete`
      → permissions `core.admin.failed-jobs.{list,show,retry,delete}` via the
      existing `SyncPermissionsAction` sync (run the sync command/seeder; super-admin
      bypasses via `Gate::before`).
- [ ] `FailedJobSearchFilter` (exception substring, min 3 chars — reject shorter with
      a validation error, mirroring `Modules/Audit/app/Filters/CreatedFromFilter.php`)
      + exact filters for uuid/queue; `ListFailedJobsAction` (QueryBuilder, sorts,
      `Pagination::perPage()`), `ShowFailedJobAction`, `RetryFailedJobAction`
      (pre-check row exists → `Artisan::call('queue:retry', ['id' => [$uuid]])` —
      array argument per the installed Laravel 13 signature; the command removes the
      failed row itself on success), `ForgetFailedJobAction`
      (`app('queue.failer')->forget($uuid)` — bool return, verified). Thin invokable
      controllers.
- [ ] `Transformers/Admin/FailedJob/FailedJobResource.php` — list shape with the
      ≤300-char server-side excerpt; show shape with full exception + redacted
      payload (D3: placeholder for `data.command`, denylist masking, plain strings).
- [ ] Error codes + `Modules/Core/lang/en/errors.php` (uuid not found, retry
      failed, search-too-short) + the other 5 locale `errors.php` files (agent-drafted,
      flagged for owner review).
- [ ] Pest: list/pagination/filters incl. min-length rejection, show 404, permission
      matrix (show-only admin → 403 on retry/delete; super-admin allowed),
      retry moves the row back onto the queue and removes it (assert via `Queue::size()`
      or the `jobs` table), forget deletes.

### Step 2 — Core schedules + backlog monitor

- [ ] `Modules/Core/config/queue.php` (auto-registered by
      `CoreServiceProvider::registerConfig()`; keys under the `core.` prefix):
      `backlog.threshold` (100), `backlog.targets` (v1: the default connection's
      default queue, resolved at runtime), `backlog.state_ttl_hours` (24),
      `failed_jobs.retention_days` (30).
- [ ] `QueueCheckBacklogCommand` (`core:queue-check-backlog`, D6): per target —
      depth check → above threshold: atomic `Cache::add` dedup (fail-open on cache
      Throwable: log + still alert) → fresh crossing fires `QueueBacklogDetectedEvent`;
      below threshold: clear state (cache failures logged, never fatal). TTL on the
      state key so a stalled scheduler can't wedge suppression (semantics per D6:
      dedup + maximum suppression period, not a strict cooldown).
- [ ] `Modules/Core/app/Contracts/Events/System/QueueBacklogDetectedEvent.php`
      (connection, queue, size, threshold) ⚠ confirm the `Contracts/Events` subdirectory
      naming at implementation.
- [ ] Wire `CoreServiceProvider::registerCommandSchedules()` (currently a stub):
      `core:queue-check-backlog` every five minutes **`->withoutOverlapping()`** +
      `queue:prune-failed --hours={retention_days * 24}` daily; register the command
      in `registerCommands()` next to `ErdGenerateCommand`.
- [ ] **Deployment verification (D7):** on the dev compose, confirm
      `php artisan schedule:list` shows both entries and the scheduler executes them;
      document the prod equivalent (`docker compose -f docker-compose.prod.yml exec
      scheduler php artisan schedule:list`) for Step 6.
- [ ] Pest: crossing → event fired once; repeat runs above threshold → no event
      (dedup); recovery run below threshold → state cleared → next crossing fires
      again; cache `add` throwing → event still fires (fail-open); TTL expiry on a
      continuously-busy queue → re-alert; both schedules registered with the
      configured retention and `withoutOverlapping` on the backlog entry.

### Step 3 — Notification integration

- [ ] `Modules/Notification/app/Listeners/Queue/NotifyAdminJobFailedListener.php` on
      `Illuminate\Queue\Events\JobFailed` — synchronous (no `ShouldQueue`), whole
      handle in try/catch → `Log::` (uuid/queue/job only), D5 semantics.
- [ ] `NotifyAdminQueueBacklogListener` on `QueueBacklogDetectedEvent` — same isolated
      shape.
- [ ] Map both in `Modules/Notification/app/Providers/EventServiceProvider.php`
      (framework events and Core contract events sit side by side there — mirror the
      existing mapping style).
- [ ] `NotificationCodeEnum` cases (`system.queue.job_failed`,
      `system.queue.backlog` ⚠ final naming at implementation) + channel-matrix
      entries in `Modules/Notification/config/notifications.php` — database channel
      always; email/telegram per the existing admin-alert entries.
- [ ] `LocaleTemplateEngine` templates ×6 locales: **external channels carry
      identifiers only** (job, queue, uuid, failed-at, pointer to the admin page) —
      no exception text outside the admin UI and the database inbox (D3); consistent
      ops terminology; agent-drafted translations flagged for owner review.
- [ ] **Alert `NotificationMessage` built with explicit per-channel data keys**
      (D3, verified): `database` may carry the exception excerpt; `email` and
      `telegram` are **always passed explicitly with identifier-only content** —
      `TelegramPayloadBuilder`/`WebPushPayloadBuilder` fall back to
      `data['database']` when their key is absent, so an omitted key would leak
      exception text into external channels.
- [ ] Bounded timeouts for external channels (D5): ⚠ verify `TelegramClient` and the
      mail transport's current timeout config; set explicit connect/request timeouts
      via config if missing.
- [ ] Pest: JobFailed (run a real failing queued job + `queue:work --once`) →
      database-channel alert row exists with admin recipients, external channels carry
      no exception text (`Mail::fake()` assertions); listener's dispatcher throwing →
      no exception propagates, `failed_jobs` row intact; backlog event → alert;
      ⚠ channel-timeout isolation test shaped per existing channel tests.

### Step 4 — Automated tests (consolidation pass)

- [ ] The per-step tests above already cover the critical set; this pass adds the
      review's explicitly named cases and runs the touched modules' suites together:
      **retry returns the job to the queue and removes the failed row** · **JobFailed
      alert reaches admin recipients, external channels carry no exception text** ·
      **permission matrix (show-only admin → 403 on retry/delete)** · **notification
      failure/timeout leaves the failed-job record intact and propagates nothing** ·
      **backlog: crossing → one alert, re-checks suppressed, recovery clears state,
      next crossing re-alerts** · **cache failure → alert still sent**.
- [ ] High set: filters + min-length search validation · forget · prune retention ·
      uuid-not-found error mapping.
- [ ] Full `Modules/Core` + `Modules/Notification` suites green; Pint; phpstan
      neutral (baseline spliced only for new-file false positives, A.1/A.3 pattern).

### Step 5 — Admin panel (`apps/admin`)

- [ ] Feature slice mirroring `src/features/audit/logs/`: route
      `src/routes/_authenticated/failed-jobs/index.tsx` (beforeLoad permission gate →
      `/403`, search-param validation), `src/features/failed-jobs/` with
      `data/routes.ts` (cache key + Locales namespace), `api/failed-job.api.ts`,
      `hooks/use-failed-jobs.ts`, `components/{columns,data-table,view-dialog}.tsx`
      (manual pagination/sort/filter per the admin data-table conventions).
- [ ] Columns per D2: uuid, job name, connection, queue, failed-at, exception excerpt;
      detail dialog (fetch gated on `core.admin.failed-jobs.show`): full exception +
      redacted payload rendered as escaped text.
- [ ] **Retry + forget buttons, each gated on its own permission**
      (`core.admin.failed-jobs.retry` / `.delete` via `hasPermission`), each opening a
      confirmation dialog with the D4 copy (side-effects warning on retry; permanent
      deletion on forget). Success/error toasts; list refresh after the action.
- [ ] Sidebar entry in `src/components/layout/data/sidebar-data.ts` General group
      (next to Audit Logs), `permission: 'core.admin.failed-jobs.list'`.
- [ ] i18n: `apps/admin/public/locales/{ar,de,en,es,fa,fr}/failed-jobs.json` +
      `sidebar.failed-jobs` key in `shared/layout.json` ×6; `node
      scripts/check-i18n-parity.mjs` green; agent-drafted translations flagged for
      owner review.

### Step 6 — Documentation & deployment verification

- [ ] `apps/backend/docs/flows/queue-visibility.md` (new): lifecycle of a failed job
      (throw → retries → `failed_jobs` → alert → retry/forget → prune), the
      retry-is-not-recovery + idempotency expectations for job authors (D4), the
      dedup/cooldown behavior, the shared-hosting caveat (§5), and the
      visibility-≠-worker-management boundary (§1.3).
- [ ] `docs/LOGGING.md` §Failed jobs: replace the "tracked follow-up" sentence with a
      pointer to the alerting + admin surface; keep the artisan commands as the
      CLI fallback.
- [ ] `infrastructure/docker/production/README.md` Day-2 operations: failed-jobs
      triage lines + the scheduler/prune verification command from Step 2.
- [ ] `docs/PLATFORM_GAPS_ROADMAP.md` §B.3: tick checkboxes with dates; record the
      surface decision + Horizon deferral (D8) in the section; tick the Phase B exit
      criterion line once B.1–B.3 all land.
- [ ] Bruno requests `api/bruno/Core/Admin/FailedJobs/` (List, Show, Retry, Forget)
      with the collection's `meta`/`auth: bearer` conventions.
- [ ] Regenerate the module dependency graph (`python3 scripts/generate-module-graph.py`)
      — a new admin feature area is added.
- [ ] CHANGELOG entry; full test suite once before declaring done (not per-edit).

## 5. Edge cases (each maps to a decision + a test)

| Scenario | Behavior |
|---|---|
| Huge exception string | List sends ≤300-char server-side excerpt; full text only in the show endpoint (D2) |
| Burst of failures (e.g. SMTP outage, 50 jobs) | One alert per final failure, landing in the admin inbox which batches naturally; no throttling v1 — accepted and documented (D5) |
| Retried job fails again | Fails like any job → new `JobFailed` → new alert; retry is honest about this (D4) |
| Double-click retry / concurrent retries | Same semantics as the CLI (`queue:retry` pops the row then pushes) — worst case a double-queued job; accepted, documented, same as Horizon (D2) |
| Unknown uuid on retry/forget/show | Mapped error via `ErrorCodeEnum` + lang errors (Step 1) |
| Cache unavailable during backlog check | Fail-open: log, still alert; dedup best-effort, never permanently suppressing (D6) |
| Scheduler stalls while queue stays busy | State key TTL expires → next run re-alerts; no wedged suppression (D6) |
| Payload contains credentials / serialized objects | Placeholder for `data.command`, denylist masking, never unserialized, escaped text (D3) |
| Queue under redis vs database driver | Surface identical — failed driver is table-based regardless (D8) |
| Shared hosting (sync queue) | Listeners run in-request; few/no failed rows accumulate — caveat documented; page identical (D8) |
| Named queues introduced later | Backlog targets are config entries; failed-jobs page shows the queue column already (D6/D2) |
| Admin with show-only permission | 403 on retry/forget routes, buttons hidden in UI (D2) |

## 6. Test plan

**Critical:** retry returns the job to the queue and removes the failed row ·
JobFailed alert reaches admin recipients with external channels carrying no exception
text · permission matrix (show-only admin → 403 on retry/delete) · notification
failure/timeout leaves the failed-job record intact and propagates nothing · backlog
crossing → one alert, suppressed re-checks, recovery clears state, next crossing
re-alerts · cache failure → alert still sent.

**High:** filters + min-length search · forget · prune retention from config ·
uuid-not-found mapping · backlog targets read from config (not hardcoded) · channel
timeout isolation.

**Human gate:** in the dev compose, dispatch a deliberately failing queued job,
receive the alert in the admin inbox, walk the page: list → detail → confirm-retry →
job processes; then confirm-forget on a second row. Tests assert behavior, not prose.

## 7. Out of scope / non-goals

- **Worker management** — start/stop/restart of `queue:work`/Horizon processes stays
  the compose stack's job (§1.3).
- **Laravel Horizon** — consciously deferred (D8); the minimal surface does not
  preclude it.
- **Metrics/observability expansion** — throughput dashboards, wait-time charts,
  per-job tracing: the health endpoint, `docs/LOGGING.md`, and Sentry already own
  aggregate observability; B.3 stays failed-job-focused.
- **External monitoring integrations** (guaranteed-delivery alerting) — best-effort
  in-app + email/telegram alerting v1 (D5); a paging/monitoring stack is a future
  operational decision, not this phase.
- **Full-text search** on exceptions — `%LIKE%` within a 30-day-pruned table is
  sufficient (D2).
- **Queue-age / throughput / latency detection** — v1 alerts on configured queue
  **depth** only (D6); measuring how long jobs sit in the queue or how fast they drain
  is a future enhancement.
- **Bulk retry/forget** — one job at a time; bulk actions arrive if a real workflow
  demands them.
- **Notification preferences for ops alerts** — admin ops alerts bypass preference
  filtering like other admin-audience alerts ⚠ mirror the `LockedNotificationCodes`
  treatment of existing admin codes at implementation.

## 8. Suggested commits (one per step, docs step may split)

1. `feat(backend/core): failed jobs admin surface with retry and forget`
2. `feat(backend/core): queue backlog monitor and failed job pruning schedules`
3. `feat(backend/notification): failed job and queue backlog admin alerts`
4. `feat(admin): failed jobs page`
5. `docs(monorepo): record B.3 queue visibility decision and ops notes`

## 9. Review trail

- v1 (2026-11-07, planning session): surface decision taken with the owner —
  **minimal failed-jobs page**, Horizon deferred (options compared with
  deployment-target and scope evidence).
- v1.1 (2026-11-07): external review **9/10, approved with revisions** — five required
  changes folded in: separate list/view/retry/delete permissions (D2); sensitive-data
  handling for payloads and exception text (D3); backlog-alert deduplication (D6);
  retry idempotency + side-effect warnings in UI copy and docs (D4); synchronous
  notification failures isolated from Laravel's failure handling (D5). Also adopted:
  the review's six-step build order, model-thinness note (D1), shared-hosting
  visibility-vs-processing caveat (§1.3, §5).
- v1.2 (2026-11-07): external review **9.5/10, approved** — five technical
  clarifications folded in: (1) `QueueBusy` has no recovery event → one scheduled
  command owns detection *and* state clearing (D6); (2) serialized `data.command` is
  never unserialized or exposed; denylist is a net, not the primary defense (D3);
  (3) bounded timeouts on external notification channels; database inbox is primary
  (D5); (4) cache-failure behavior defined as fail-open, dedup best-effort, atomic
  `Cache::add` (D6); (5) exception excerpts truncated server-side at ≤300 chars (D2).
  Minor items adopted: confirmation dialogs for retry and forget (D4), monitored
  targets from config (D6), configurable prune retention (D7), list columns
  specified (D2/Step 5), alert-delivery failures logged without payload contents
  (D5), timeout isolation test (Step 4). **Implementation gate: final read-through of
  this document before coding.**
- v1.3 (2026-11-07): final review — **9.5/10, approved for implementation** after
  minor corrections; all seven folded in, several resolved by verifying the installed
  framework instead of leaving assumptions: (1) `queue:retry` verified on the
  installed Laravel 13.38 as an **array-argument** command (`{id?*}`) that exits 0 on
  not-found and removes the failed row itself on success → the action pre-checks
  existence (404 path) and retry keeps `Artisan::call(..., ['id' => [$uuid]])`; the
  reviewer's scalar-uuid suggestion applied to older Laravel versions.
  `queue:forget` verified as `{id*}` returning FAILURE on not-found → forget switched
  to the failer API's bool-returning `app('queue.failer')->forget($uuid)` — no
  exit-code or console-output guessing (D2). (2) `FailedJob` model configuration made
  explicit: `$table`, `$timestamps = false`, pk `id`, `failed_at` datetime cast,
  never treated as a managed timestamp (D1). (3) `->withoutOverlapping()` on the
  backlog schedule; `Cache::add` dedups crossings while overlap protection guards the
  whole check-then-act workflow — together closing the A-adds/B-clears/A-alerts race
  (D6). (4) Monitoring reworded as **queue-depth** monitoring (depth ≠ throughput);
  queue-age/latency detection deferred to §7 (D6). (5) Channel-specific alert content
  verified as already supported via channel-keyed `NotificationMessage::$data`, with
  the `Telegram/WebPush → data['database']` fallback documented as a leak risk that
  mandates always-explicit `email`/`telegram` keys (D3). (6) State TTL documented as
  dedup + maximum suppression period, not a strict cooldown (D6). (7) Permission
  naming resolved to the codebase convention **`core.admin.failed-jobs.show`** (route
  name `failed-jobs.show`, matching Audit's `logs.show`; no `show`→`view` mapping
  invented) (D2). Estimate calibrated to 1.5–2 developer days.

## 10. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` — §B.3 (this phase's source checklist), gap
  register #5 (the verified blind spot), Phase B exit criteria ("a failed job is
  visible and alerts"), §6 policy 4 (self-hosting guarantees).
- `docs/LOGGING.md` §Failed jobs — the manual ritual this phase automates; updated in
  Step 6.
- `docs/TELEGRAM_NOTIFICATIONS.md` — telegram channel background for the alert path.
- `infrastructure/docker/production/README.md` — Day-2 ops + uptime monitoring
  (A.3 health endpoint) this phase extends.
- `infrastructure/sharedhosting/README.md` — the no-Redis/no-workers constraint that
  shaped the surface decision.
- `docs/plans/gap-b1-invoices.md` — format precedent for this document; B.1 Phase 3
  adds queued notification email jobs, which are exactly the workloads this surface
  makes visible.
