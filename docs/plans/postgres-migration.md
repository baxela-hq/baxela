# PostgreSQL Migration Plan (rev 8.2 — IMPLEMENTED)

Status: **implemented — phases A–E landed (2026-11-22).** The stack runs
`postgres:18-alpine` in both compose files, MySQL is gone from the backend,
and the full suite is green on both engines (1531/1531 on the live
PostgreSQL server; the SQLite plane keeps its environmental-only failures).
CI carries a strict full-suite PostgreSQL leg. See the implementation
record at the end of this document for phase commits, live-server
corrections, and the honest follow-up list.

Rev 8.2 remains the authoritative reviewed architecture: it completed
§3.4.1a with atomic, lock-serialized pruning against a durable retired-ID
registry, and sharpened the savepoint-bookkeeping semantics.

Owner decisions (unchanged): **D-A** fresh start · **D-B** SQLite default test
plane + full PG CI leg · **D-C** before tenancy Phase 2; RLS deferred (§9) ·
**D-D** clean cut · **PostgreSQL 18** primary.

## Finding-response table (rev 8.2 additions)

| # | Clarification | Response |
|---|---|---|
| C3 | Pruning must be atomic against concurrent mutation/restoration; retired-ID registry authoritative | §3.4.1a: pruning runs **inside the same cursor locking protocol** — `SELECT … FOR UPDATE` on the cursor row → **recheck every precondition under the lock** → atomically **insert the retired-ID registry row and delete the cursor row in one transaction**. Every journal append and restoration checks the registry **under the same serialization boundary** (cursor-row lock, then registry if the row is absent). Once retirement commits, no concurrent or subsequent mutation/restoration for that ID can succeed. The registry is **authoritative gating state, not an audit record**; ID non-reuse is enforced by the identity/schema rules (bigserial). Race test: matrix 83 (exactly one outcome) |
| C4 | Savepoint rollback restores the pre-savepoint lock-record snapshot | §3.4.1b: bookkeeping rollback is specified as **snapshot restoration** — acquisitions made before the savepoint are preserved; only entries introduced inside it are undone |

All prior rounds remain folded and resolved: R1 F1–F11, R2 F1–F7, R3 F1–F5,
R4 F1–F4, R5 F1–F4, R6 F1–F3, R7 F1–F3, R8 F2-A/F2-B, R8.1 C1/C2.

---

## 1. Verified codebase facts

Backend paths relative to `apps/backend/`.

- **P1.** `config/database.php` ships a `pgsql` block; remove mysql/mariadb
  blocks + `Pdo\Mysql`; no read/write split exists.
- **P2.** Both Dockerfiles already install `pdo_pgsql` + libpq.
- **P3.** 117 migrations compile on PG; 51 enums → varchar+CHECK; 15 JSON
  columns whole-read; zero JSON-path operators.
- **P4.** Zero fulltext/procs/triggers/views/ENGINE=/MySQL-only functions.
- **P5.** Raw-SQL rewrite sites (transfer locks, connection_id, processlist,
  backticks, substr ×2).
- **P6.** Portable: 43 lockForUpdate, upsert, groupBy, boolean orderByRaw,
  whereDate, pings.
- **P7/P23.** insertOrIgnore conflict keys (auth_user_devices (user_id,
  device_name); webhook_deliveries (endpoint_id, event_id)); DD13 scopes PG.
- **P8/P9.** LIKE ×4; ci-uniques ×5; `AccessGateway.php:66` LOWER read path.
- **P10/P11.** Subsystem semantics to preserve (§3 headers).
- **P12/P13.** Test plane + CI shape (Redis docker-run precedent; pdo_pgsql
  missing; env-over-phpunit; per-file RefreshDatabase; SET lock_timeout; psql
  scratch-DB recipe).
- **P14/P15.** Compose dev/prod; mysqldump backup doc; port conventions.
- **P16/P17.** database drivers for session/queue/cache; Redis broadcast-only;
  Octane persistent workers; DisconnectFromDatabases deliberately off.
- **P18/P19/P20.** Worker containers; dead FK_CHECKS guards; docs surfaces.
- **P21.** Unsigned audit (375 calls; PostgresGrammar drops the flag silently).
- **P22.** Identifier audit (three names at exactly 63 bytes).
- **P24.** Topology: single host, compose; per-container PID namespaces;
  boot_id host-global; sibling /proc unreadable; multi-host unsupported.
- **P25.** Dispatch-path audit + registry + enforced invariant with the
  §3.3.0a discovery boundary; classification unchanged (search journal /
  webhooks / media = required with durable records, processors,
  retry/recovery; notifications/email = best-effort, preserved; HTTP =
  reads).

---

## 2. Design decisions

Unchanged: **DD1** portable LOWER LIKE · **DD2** collation matrix · **DD3**
to_char gating · **DD6** postgres:18-alpine, pg_isready, loopback :5432,
volume `postgres-data:/var/lib/postgresql`, persistence test · **DD7** pgsql
test leg · **DD8** identifier rule 63/≤58 + three renames · **DD9/DD9b**
unsigned range table + exact gated grammar compilation · **DD10**
pg_constraint + pg_indexes audit · **DD11** full PG CI leg · **DD13** scoped
ON CONFLICT targets.

---

## 3. Subsystem redesigns

### 3.1 Transfer import locking — fenced write units

Table `transfer_locks` (name UNIQUE, owner_token, generation, heartbeat_at,
expires_at). **`clock_timestamp()` in every expiry/validity predicate.**
Write unit: one transaction — `SELECT … FOR UPDATE` → verify token +
generation + live lease → protected writes → COMMIT. Acquisition via
savepoint-nested INSERT; reclaim = the only generation increment
(expiry-gated; reclaimer `SET LOCAL lock_timeout='2s'`); renewal requires a
live lease; release teardown-first, best-effort. **Invariants I1–I9.**
Regression matrix 39.

### 3.2 Search-cutover worker identity and death proof

Admissions gain `instance_token` (drop db_connection_id/processlist/
connection_id). Release updates token-guarded.

**3.2.1 Death-proof matrix (topology-scoped):** same container = full `/proc`
proof (pid start-time with PID-reuse recognition; pid-1 recreation; boot_id
reboot kill-switch) · cross-container = boot_id only, otherwise unprovable →
**fail-closed** (stale heartbeat is a pre-filter, never proof; drain
bounded-aborts) · multi-host unsupported.

**3.2.2 Operator override `--assume-dead <token>`:** super-admin-gated; audit
records identity, reason, token, timestamp, result; mutation is
token(+generation)-conditional (0 rows on reclaim ⇒ no-op); token-not-current
⇒ explicit `superseded` outcome, nothing released.

**3.2.4 Same-generation writers:** generation-scoped writer leases
(heartbeat-gated claims, expiry-gated reclaim); writers re-read the journal
inside the lease transaction and write journal-derived state (stamped with
the §3.4.1 per-product cursor); retired generations never reactivate.
Engine-side ordering is not claimed — §3.4 reconciler.

### 3.3 External side effects — classification, durability, publication fencing

**3.3.0 Queued-work classification** (per P25's registry): required work has
a durable in-transaction record; notifications/email best-effort (accepted);
`afterCommit` is a latency optimization only. Crash-window test: matrix 47.

**3.3.0a Dispatch registry — discovery boundary.** The architecture test
statically scans exactly: direct dispatch APIs (`dispatch(`, `dispatchSync(`,
`dispatchAfterCommit(`), `event(`/`Event::dispatch(`, EventServiceProvider
`$listen` maps (registered listener classes are the audit unit), observers
(`->observe(`, `#[ObservedBy]`), schedule/console registrations, and
`->afterCommit(` markers. **Not claimed discoverable:** dynamic class names,
variable/facade bus calls, string handlers, custom wrappers — these must
route through the single sanctioned dispatch wrapper (registered by
construction) or carry an explicit registry declaration; the test also fails
syntactically-detectable variable-target dispatch outside the wrapper.

**3.3.1 Media adapter mechanics:** local publish = same-filesystem staging
sibling + atomic `rename()` (same-device asserted); remote adapters: staging
key → copy → **verify** (content-hash/ETag) → delete staging, idempotent
re-runs, adapter-agnostic `publish(staging → final)` contract. Sweep
eligibility = age beyond grace (24 h) AND not referenced by a non-terminal
outbox row holding a live lease. Sweep is cleanup, not fencing.

**3.3.2 Publication fencing — atomic terminal transition.** Content-addressed
immutable final keys; staged bytes hash-verified **before** publication;
claim-conditioned terminal UPDATE and the media-row path update are **one
transaction**, affected-row count checked before the media update — zero
rows ⇒ exception ⇒ rollback ⇒ **no authoritative change at all**.
**Invariant:** only the current claim token and epoch can make a published
object authoritative in the database. Partial failure rolls back both
updates. Local and remote adapters share the fencing invariant.

### 3.4 Durable search reconciler — per-product cursor protocol and SLO

Scheduler-registered reconciler (default 5-min interval; own identity +
heartbeat) overwrites stale documents with journal-derived state under a
freshly claimed writer lease; per-item retry/backoff; persistent failure
recording; alert threshold; nightly full reindex backstop. **Convergence SLO
(not guarantee):** ≈ 2 × interval under the enumerated assumptions
(scheduler granularity; drift-scope scan; oldest-lag-first fairness, batch
K=500, ⌈drift/K⌉-pass revisit; retry/backoff; engine availability with the
SLO clock suspended-and-surfaced). **Metric:** convergence lag = age of the
oldest mutation not yet reflected in the serving index, per product against
the authoritative cursor.

**3.4.1 Per-product ordering protocol.** `catalog_search_product_cursors`
(`product_id` PK, `cursor` BIGINT). Every journal mutation, **in the same
transaction** as its journal insert: acquire the product's cursor row
`FOR UPDATE` (initialization per §3.4.1a), insert the journal row, advance
`cursor = cursor + 1` under the lock; release at commit ⇒ cursor values are
assigned in **commit order**; aborted increments are never visible. Documents
are stamped with the per-product cursor; the reconciler compares against this
cursor — the global sequence id is a diagnostic key only.

**3.4.1a Cursor-row initialization, lifecycle, and retirement.**
Initialization: `SELECT … FOR UPDATE` → on null, INSERT inside a nested
transaction (**savepoint**) → on unique violation, savepoint-only rollback
(outer transaction stays usable; cross-driver-safe) → re-lock, which
**blocks on the winner's uncommitted row until it resolves** — concurrent
first mutations serialize exactly like later ones; no lost increment;
cursor order == commit order (matrix 75–76).

**Lifecycle — deletion, tombstones, restoration:**
- **Deletion** is itself a journaled mutation: the delete path acquires the
  cursor lock, appends a **tombstone** journal operation, advances the
  cursor, commits; writers/reconcilers render the product absent.
- The cursor row is **retained** on deletion — never reset, never dropped
  opportunistically — it is the ordering anchor for any later journal fact.
- **Restoration** (soft-delete restored) is another journaled mutation on
  the retained row; **recreation** (a new product id) initializes a fresh
  cursor row via the initialization protocol.

**Retirement (pruning) — final implementation invariant (C3):**
- **Semantics:** pruning a product cursor row **permanently retires that
  product ID from further journal mutation and restoration**, and records
  it in a **durable retired-ID registry** (`catalog_search_retired_products`,
  product_id PK + retired_at + maintenance metadata).
- **Protocol — same serialization as mutations:** maintenance executes,
  in one transaction: `SELECT … FOR UPDATE` the cursor row (blocking
  against any in-flight mutation/restoration of that product, exactly as
  mutations serialize with each other) → **recheck every precondition
  while holding the lock** (terminal tombstone as the last journal
  operation; zero unresolved journal operations; no remaining indexed
  document — tombstone applied and deindex verified against the serving
  generation; retention window elapsed) → **atomically insert the
  retired-ID registry row and delete the cursor row** → commit. The
  registry insert and cursor delete are one transaction, so there is no
  intermediate state in which the cursor row is gone but retirement is not
  recorded (or vice versa).
- **Mutation/restoration check — same serialization boundary:** every
  journal append and every restoration acquires the product's cursor-row
  lock as its first step (per §3.4.1b). If the row is present, proceed
  normally. If the row is **absent**, the path consults the retired-ID
  registry **within the same locked sequence**: a registry hit ⇒ hard error
  (fail closed); no hit ⇒ the §3.4.1a initialization path applies. Because
  retirement only ever commits while holding the cursor-row lock and
  deletes the row inside that transaction, an append racing retirement
  either (a) holds/awaits the lock first and commits its mutation — the
  maintenance transaction's precondition recheck then observes the new
  journal operation and **refuses to prune**; or (b) loses the race —
  retirement commits, the append then finds no cursor row, hits the
  registry, and **fails closed**. Exactly one outcome is possible; the
  race test (matrix 83) proves both branches and the impossibility of the
  interleavings between them.
- **Registry is authoritative state, not an audit record:** it gates every
  append/restoration; it is not merely logged. **ID non-reuse** is enforced
  by the identity/schema rules — product ids are bigserial and are never
  reallocated, so a retired ID can never legitimately reappear (matrix 83
  also asserts the append-after-retirement hard error).
- **Fail-closed bias:** if any precondition is uncertain — or the cursor
  row is absent **without** a registry entry but with unresolved history
  (an inconsistency indicating prior partial failure) — the row is retained
  / the maintenance action refuses, and the anomaly is surfaced for
  operator review rather than guessed about.

**3.4.1b Global lock-ordering invariant.** **Every transaction that mutates
journaled product state — whatever its entry point — acquires the product
cursor lock(s) first.** Covered paths: primary mutation actions, **nested
actions, event listeners, Eloquent observers, helper traits/services, batch
operations, import/seeder paths.** Rules: cursor locks before any other row
lock on journaled state; multiple cursor locks in ascending product_id
order; **no hidden or deferred cursor acquisition** (a journal append
arriving after the transaction already holds a conflicting row lock is a
protocol violation and fails closed).

Enforcement — two layers:
- *Static:* the §3.3.0a registry/architecture test extends to a **sanctioned
  mutation-paths registry** — any class calling the journal-append seam
  (listeners/observers/nested helpers included) must be registered or the
  build fails.
- *Runtime:* the journal-append API keeps a **transaction-scoped,
  connection-specific, savepoint-aware record** of cursor locks acquired in
  the current transaction; an append for a product whose cursor lock was
  not acquired in this transaction **throws** (fail closed). Bookkeeping
  semantics (C2/C4):
  - **Connection-specific:** the record lives per database connection
    instance, not in global/static state — two connections in one process
    never see each other's locks.
  - **Savepoint-aware — snapshot semantics:** beginning a nested
    transaction (savepoint) snapshots the current lock-record set;
    `ROLLBACK TO SAVEPOINT` **restores the record to that pre-savepoint
    snapshot** — acquisitions made **before** the savepoint are preserved,
    and only entries introduced inside it are undone. The post-rollback
    guard state therefore exactly matches the locks PostgreSQL actually
    retains (row locks taken inside an aborted savepoint are released;
    those taken before it are not).
  - **Cleared on lifecycle boundaries:** the record is cleared on **outer
    commit**, **outer rollback**, and **connection lifecycle changes** —
    disconnect, reconnect, purge. Under Octane's persistent workers this
    is the same reset discipline as the tenancy context: a later request
    on the same worker starts with empty bookkeeping.
- **Deadlock retries restart the complete transaction** at the outermost
  mutation boundary — cursor locks, journal appends, business writes —
  never just the journal fragment; bounded retries with backoff, then clean
  failure.
- **Exceptions:** any path that genuinely cannot order cursor-first requires
  a documented justification entry in the registry (deadlock analysis
  included), reviewed like a permission grant.

---

## 4. Phased delivery

Unchanged structure; heavy commands sequenced, `nice -n 10`; analyse before
Pest; full suite once per phase; changed-file list + output between phases.

- **Phase A — subsystem rewrites on MySQL (zero behavior change):** §3.1–§3.4
  (lock table, heartbeats, writer-lease expiry, cursor table +
  initialization + lifecycle + **lock-serialized retirement + retired-ID
  registry + registry-gated append/restoration** + runtime guard with
  snapshot-restore bookkeeping + deadlock-retry boundaries, media outbox +
  fenced atomic publication + sweep, reconciler + scheduler, dispatch
  registry + wrapper + architecture test incl. mutation-paths registry).
  **Accept:** suite green (SQLite + MySQL); grep gate; SQLite-runnable
  matrix rows; registry/discovery/lock-order/bookkeeping/retirement tests.
- **Phase B — portable fixes on MySQL:** LIKE sites, stats gating, backtick,
  functional indexes + trim rules, scoped conflicts + concurrency tests,
  grammar class. **Accept:** per-engine DDL verification; collation deltas.
- **Phase C — infra + config cut-over:** compose ×2, database.php, env
  examples, psql gate recipe. **Accept:** healthy stack; migrate + seed.
- **Phase D — validation on PG:** full suite (local + CI leg, zero new
  skips); driver-gated tests; octane gate; e2e; fresh + rollback;
  worker-kill mid-import; persistence; identifier audit; unsigned
  storage/CHECK/PK-FK/`->change()`/reconnect; clock regression; topology
  tests; crash-window test; reconciler repair/fairness/retry/SLO tests;
  cursor ordering + initialization + lifecycle + **retirement race** +
  pruning-precondition tests; publication-fencing suite; sweep-vs-live;
  override safeguards; pg_dump/restore; analyse + pint.
- **Phase E — docs:** all P20 surfaces; backup → pg_dump/pg_restore; 64→63
  identifier rule; runbook (reconciler/sweep/override/SLO/registry/
  lock-ordering/cursor-retirement rules).

## 5. Change surface

Code: config/database.php; TransferImportSession, TransferRunContext,
SearchMutationGate, OwnerIncarnation, ProductIndexWriter, RecalculatesProductRating,
ShowStatsAction, LIKE ×4, insertOrIgnore ×2, grammar class + extender, media
outbox processor + atomic fenced publication + sweep command, reconciler
command + scheduler registration, dispatch registry + sanctioned wrapper +
architecture test (incl. mutation-paths registry + journal runtime guard
with snapshot-restore bookkeeping + deadlock-retry boundaries), product-
cursor table + initialization/lifecycle + **retired-ID registry +
retirement maintenance**, two new migrations (+ writer-lease expiry
columns), three identifier renames, two test files, gate-script docblock.
Infra: compose ×2, env examples ×3, local .env copies. Docs: Phase E list.
CI: ci.yml DD11 leg. Untouched: Dockerfiles, php.ini, nginx, scripts/**,
api/**, apps/admin/**, apps/storefront/**.

## 6. Acceptance matrix

| # | Scenario | Expected | Phase |
|---|---|---|---|
| 1–82 | rev-7/8/8.1 rows (all prior classes: search/LIKE/stats/collation/uniques; locking incl. TOCTOU, savepoint, clock; death-proof topology; zombie fencing; scoped conflicts; identifier audit; grammar; unsigned; full PG suite; persistence; crash window; dispatch registry + discovery; reconciler + SLO + fairness; media fencing incl. atomic transition + hash verify; cursor two-txn ordering, rollback reuse, multi-product ordering, concurrent init, lifecycle, hidden-path guard, batch ordering, deadlock restart, mutation-paths registry; pruning preconditions; bookkeeping savepoint + lifecycle) | unchanged | A–D |
| 83 | **Retirement race (rewritten):** maintenance raced against a journal append/restoration on the same product | exactly one outcome — mutation commits first ⇒ precondition recheck (under the lock) refuses the prune; retirement commits first ⇒ the append/restoration finds no cursor row, hits the retired-ID registry, and fails closed; also asserts append-after-retirement hard-errors and registry+delete atomicity | A |
| 84 | **Bookkeeping snapshot-restore:** lock A acquired → savepoint → lock B acquired → `ROLLBACK TO SAVEPOINT` → append for A / append for B | append for A succeeds (pre-savepoint acquisition **preserved**); append for B throws (in-savepoint acquisition undone — matches PG's actual lock state) | A |
| 85 | **Bookkeeping lifecycle:** outer commit / outer rollback / connection reconnect (Octane worker reuse) | record cleared; later appends fail closed until re-acquired; no cross-request/cross-connection leakage | A |

## 7. Risks and accepted trade-offs

Prior list (collation deltas; 2⁶³ ceiling; widening; grammar subclass; CI ≈2×;
SQLite status quo; cross-container unprovability; convergence-not-ordering;
paused-worker re-execution made harmless; best-effort notification loss;
SLO-not-guarantee; immutable media paths; registry discipline; cursor
serialization cost; retained cursor rows; outermost-retry nesting constraint;
pruning's fail-closed retention bias) — unchanged; the retired-ID registry
adds one authoritative gating table consulted only on the cursor-row-absent
path (cost: one indexed lookup per first-mutation or post-retirement
attempt).

## 8. Post-migration optimizations (out of scope)
ILIKE/pg_trgm · citext/ICU · JSONB GIN · partial indexes · EXCLUDE ·
UNIQUE NULLS NOT DISTINCT.

## 9. RLS deferral (unchanged)
Tenancy rev 4.1 ships app-level isolation on PG; RLS follows as a dedicated
phase (fail-closed policies, CentralOperations bypass GUC, driver-gated
policy migration pass, GUC lifecycle on TenantContext chokepoints, pg-only
test leg).

## 10. Octane / PG session state
`SET LOCAL` inside transactions only; no session-level SETs; tenancy GUC
lifecycle recorded; RESET ALL as recovery lever; DisconnectFromDatabases
deliberately off; prepared-statement plans safe; guard bookkeeping follows
the same per-request reset discipline.

## 11. Verdict
Architecture approved; all review rounds (R1 through 8.2) folded in.
**Implemented — see the implementation record below.**

---

## Implementation record (2026-11-22)

Phases A–E landed, in order, each closing with tests + static analysis:

| Phase | Commit | Contents |
|---|---|---|
| A | `bca07cdb`, `1eb1427d`, `f0c2a911` | transfer lease table + fenced write units, per-product cursors + retired-ID registry, worker heartbeats + /proc death proof, heartbeat-gated writer leases, reconciler, media outbox, dispatch registry + sanctioned wrapper + architecture test |
| B | `e7662630` | portable LOWER() search, driver-gated stats, functional ci-unique indexes ×5, scoped ON CONFLICT, PostgresUnsignedGrammar |
| C | `0895a822` | compose cut-over to postgres:18-alpine, database.php clean cut, env examples, gate docblock; live-server corrections (serial-family widening guard, register()-phase grammar wiring) |
| D | `5eb5bf9f` | CI full PostgreSQL leg, PostgresValidationTest (identifier audit / clock regression / CHECK parity / reconnect), lockout re-gate, rollback/persistence/dump/restore/octane-gate verified on the server |
| E | `8096443b` | docs sweep + env-example finish |
| tail | `677edcf0` | full-suite isolation closed: PicksTranslation column-default bug, transfer-installation fillable bug, shipping tie-break, ERD type_name, sequence-real test fixtures — 1531/1531 on PG, CI leg made strict |
| ops | `36f4db44` | operator runbook (`docs/runbooks/postgres-operations.md`) |

**Amendments made during implementation** (all consistent with the
reviewed architecture):

- Unsigned widening skips auto-increment columns — the serial family
  already lands one storage size up, and widening `increments()` broke
  the migrations table (found on the live server, not statically).
- The grammar resolver registers in `register()`, not `boot()`: a
  boot-time DB touch otherwise caches the stock grammar.
- Variants `down()` uses plain `dropIfExists` — Laravel's SQLite table
  rebuild cannot round-trip expression indexes (`pragma_index_info`
  yields no columns for them).
- MySQL's functional-index branch uses the double-paren form; the
  postgres/SQLite branch single-paren (per-engine DDL verification).
- `folders` migration `down()` fixed: `dropIfExists(FolderSchema::TABLE)`
  — the `::class` argument never dropped anything (silently swallowed
  by IF EXISTS on MySQL; rollback cycles now complete).

**Honest follow-up list:**

1. `--assume-dead <token>` operator override (§3.2.2, matrix 55–56):
   designed, not implemented — matters only when an unprovable-dead
   admission blocks a cutover.
2. Sentry scope regression from `edeb603b` (pre-existing; the octane
   gate flags it on both engines) — tracked separately from this
   migration.
3. Deferred by design: RLS (waits for the tenancy rollout, §9) and the
   §8 optimizations (`citext`/ICU, `pg_trgm`, JSONB GIN, partial
   indexes, `EXCLUDE`, `UNIQUE NULLS NOT DISTINCT`).
4. The e2e workflow's first PostgreSQL run lands with the next CI pass
   (the compose boot path it exercises is verified locally).
