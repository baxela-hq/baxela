# PostgreSQL Operations Runbook

Operator procedures for the platform's coordination subsystems delivered
with the PostgreSQL migration (docs/plans/postgres-migration.md). These
are the knobs a running deployment actually touches.

## Stack

- `postgres:18-alpine` in both compose stacks; the named volume mounts at
  `/var/lib/postgresql` (PG-18 layout — NOT the pre-18 `…/data` path).
- Superuser = `POSTGRES_USER` (derived from `DB_USERNAME`); there is no
  separate root-password variable.
- Dev loopback port: `127.0.0.1:${BACKEND_DB_PORT:-5433}:5432`. Prod
  publishes nothing by default.
- Health: `docker compose exec postgres pg_isready -U baxela -d "$DB_DATABASE"`.
- Backup/restore: `pg_dump -Fc` into a FRESH database, never in place —
  see `infrastructure/docker/production/README.md`.

## Transfer import leases (§3.1)

The destination-module lease lives in `core_transfer_locks`
(name/owner_token/generation/heartbeat_at/expires_at). All expiry math is
DB-clock (`clock_timestamp()` on pgsql).

- **Contention**: a second import into the same module retries within
  `TRANSFER_LOCK_TIMEOUT` (default 10 s), then fails with
  `import_in_progress`. No operator action; retry after the first import.
- **Stuck lease** (crashed importer): expires after
  `TRANSFER_LOCK_LEASE_TTL` (default 60 s) and the next import reclaims
  it — the fencing `generation` advances; the old run, if it resumes,
  fails closed with `lock_session_lost`. Do NOT hand-edit rows.
- Tuning: `TRANSFER_LOCK_TIMEOUT`, `TRANSFER_LOCK_LEASE_TTL` in the root
  `.env`.

## Search coordination (§3.2–§3.4)

Tables: `catalog_search_gate`, `…_admissions`, `…_writer_leases`,
`…_operations` (journal), `…_product_cursors`, `…_retired_products`,
`…_worker_heartbeats`.

- **Reconciler**: `catalog:search:reconcile` runs every 5 min
  (`MEILISEARCH_RECONCILE_INTERVAL`), batch 500
  (`…_RECONCILE_BATCH`), oldest-lag-first. Convergence SLO ≈ 2 × interval;
  3 consecutive failing intervals (`…_RECONCILE_ALERT_AFTER`) log an
  alert (`search reconcile convergence breached`). The nightly
  `catalog:reindex` is the absolute backstop — after any engine outage,
  run it once manually if the alert fired. Each pass also prunes worker
  heartbeat rows older than 24 h that hold no open admission (workers get
  a new row on every process start).
- **Meilisearch unavailable**: writers and the reconciler back off; the
  journal stays authoritative; convergence pauses (SLO clock suspended)
  and resumes automatically.
- **Dead admissions**: reclamation requires a stale heartbeat (60 s,
  `MEILISEARCH_WORKER_STALE_AFTER`) AND /proc incarnation proof. A
  paused-but-alive worker is never reclaimed; unprovable cases fail
  closed (drain timeout aborts the cutover safely). A cutover blocked by
  an unprovable admission aborts safely at its drain timeout — restart
  the cutover after the worker's host is verifiably gone.
- **Operator override (last resort)** — when the worker genuinely cannot
  be running but its death cannot be *proven* from this host (another
  container, an unreadable `/proc`):

  ```bash
  php artisan catalog:search:assume-dead <instance_token> --reason="<why it cannot be running>"
  ```

  It lists the recorded worker incarnation (boot id, container, pid,
  process start, last heartbeat, open admission ids) and requires an
  interactive confirmation — non-interactive runs are refused. Only
  admissions that are still open AND belong to that exact process
  incarnation are released; a stale token releases nothing and reports
  `superseded`, an unknown one `not-found`. Every attempt — including
  refused ones — writes an audit row (`search_admission_override`) in
  the same transaction as the release, so an override can never take
  effect unaudited. Establish out of band that the worker is gone
  before running it: a worker that merely paused and resumes finds
  itself fenced out of further writes.
- **Cursor retirement** (pruning a product from the journal forever):
  the maintenance action requires a terminal tombstone, zero unresolved
  operations, a confirmed deindex, ID-non-reuse (bigserial), and the
  retention window; any uncertainty keeps the row. Retirement and the
  registry write are one transaction under the same row lock.

## Media publication outbox (§3.3)

Tables: `media_media_outbox` (claim token + epoch, status, staging/final
keys).

- **Processor**: `media:outbox:process` every minute (uploads also run it
  inline post-commit — that path is latency only, the outbox row is the
  durability). A crashed processor's claim is re-claimable after 300 s.
- **Failed entries** (`status=failed`, e.g. staging-hash mismatch): the
  staging file is KEPT for review; investigate then delete the row's
  staging file or fix the source and re-create the upload.
- **Sweep**: `media:outbox:sweep` daily — deletes staging files older
  than 24 h (`--grace=N` hours, `--dry` to list) that no non-terminal
  outbox row references. It is cleanup, never fencing.

## Dispatch registry discipline (§3.3.0a)

`Modules/Core/config/dispatch.php` classifies every queued class:
REQUIRED entries must carry a durable in-transaction record (journal or
outbox); BEST-EFFORT entries accept loss (notifications). The
architecture test fails the build for an unregistered `ShouldQueue`
class or variable-target dispatch outside `SanctionedDispatch` /
`NotificationDispatcherInterface`. Treat a registry diff like a
permission grant in review.

## Lock-ordering rule (§3.4.1b)

Every transaction that mutates journaled product state takes the
product's cursor row `FOR UPDATE` FIRST (ascending product_id when
several). This is enforced statically (mutation-paths registry +
architecture test) and at runtime (the journal-append guard throws
`search_cursor_lock_not_held`). If a path genuinely cannot comply, it
needs a documented exception entry in the registry — reviewed, not
sneaked in.

## Octane persistent workers

State must never bleed between the requests a worker serves. Two
per-request listeners lead `RequestReceived` in `config/octane.php`:

- `FlushSentryScope` starts every request from a clean Sentry scope
  (tags, user, breadcrumbs) before the request swap. The Sentry
  integration's own per-request isolation is only active when a DSN is
  configured, while the enrichment provider writes to the scope
  regardless — without this listener a DSN-less worker collected every
  request's tags and breadcrumbs on one scope for its whole lifetime.
  Guarded by `Modules/Core/tests/Unit/Octane/FlushSentryScopeTest.php`.
- `FlushExtensionState` re-reads extension state.

Both are regression-proofed by the isolation gate (`scripts/octane-
isolation-gate.php`; recipe in its header, PostgreSQL-flavoured). When
touching anything request-scoped, restart the gated instance before
reading its verdict — a still-running instance keeps serving the code
it started with.
