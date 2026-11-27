# Multi-Tenancy Implementation Plan (rev 4.10 — architecture approved)

Status: **architecture approved by the external review (9.7/10, eighth round); implementation
in progress.** The review approved rev 4.10's architecture and carried one implementation-contract
clarification — reference detachment follows the same generation-first lock ordering — which is
folded into §6.4; no further architecture revision is required. The architecture and the rev 4.2
inventory base are unchanged (full code inventory taken 2026-11-24 on commit `1305e55b`, after
the PostgreSQL migration). On 2026-11-25 the owner authorized implementation of Phases 2–8 and
waived the between-phase review gate; each phase is still verified (static analysis and both test
planes) before the next builds on it. **Progress: Phase 1 landed on 2026-11-25 (its Octane-era
pieces were withdrawn on 2026-11-26 by the php-fpm revert, rev 6); Phase 2 landed on 2026-11-27;
Phases 3–8 are not started** —
the owner stopped the work after Phase 1 and moved the implementation to another agent. The
branch `feat/multi-tenancy` carries the phase; open Phase-1 verification debts are listed at the
end of the Implementation Record. Progress and deviations from the plan text
are logged in the Implementation Record at the end of this document.

Architecture (unchanged): **single database, `tenant_id` row scoping** · host/subdomain tenant
resolution · global users + per-tenant memberships · spatie-permission teams keyed by `tenant_id` ·
per-tenant payment credentials from day one · phased rollout, each phase leaves the suite green.

Framing (unchanged): **a tenant is an independent business/store, not an independent customer
database.** Global customers shop across stores; staff memberships, roles, and permissions are
tenant-specific.

Rev 4.2 changes from rev 4.1 (architecture untouched):
- Owner decisions recorded (§0): store admins see one store; only the platform super-admin operates
  across stores, with no merged view; a multi-store owner works one store at a time; the tenant
  filter applies by request on one model per table, not through model subclasses.
- Five proposals marked **to confirm** (§0): shared search index, platform base currency,
  platform-wide extensions, per-store media keys, platform vs store settings.
- Facts refreshed after the PostgreSQL migration (§1: F1, F3, F7, F9, F10, F11, F13 amended;
  F28–F47 new).
- Every table classified: 91 store-owned / 39 installation-level (§4.4); all 90 unique indexes
  dispositioned (§4.5); all 17 translation tables gain `tenant_id` (supersedes rev 4.1 §4.3).
- Models: one line per model (`use TenantScopedTrait;`), registered through `bootTenantScopedTrait()`
  because two models already override `booted()` (§2.1, F32).
- `ResolveTenant` must run ahead of `ApplyDatabaseCredentials` and route binding (§3.1, F35).
- Payments reuse the existing `payment_driver_credentials` table — no new table (§6.3, F42).
- New sections for single-store assumptions the inventory found: store runtime (§6.7), search
  (§6.8), order documents (§6.9), transfer (§6.10), extensions (§6.11), notifications, caches and
  rate limits (§6.12).
- Phases 7 → 8 (§8); isolation matrix 42 → 68 rows (§10); ten known defects listed with the phase
  that fixes each (§9.8).

Rev 4.3 changes from rev 4.2 (architecture untouched; per the external review):
- B1: execution boundaries split into three categories — platform operations (super-admin +
  `CentralOperations`), tenant operations (MODE_TENANT, permission-guarded), and tenant
  provisioning (self-serve, single transaction, no central mode) — resolving the onboarding and
  member-management conflict (§6.5, §4.1, §9.7).
- B2: platform-role uniqueness enforced at the database level by a portable partial unique index;
  `NULLS NOT DISTINCT` is dropped entirely in favour of partial indexes (PostgreSQL and SQLite
  both support them); minimum PostgreSQL version stated; SQLite execution strategy defined
  (§4.5, §5, §4.2).
- B3: idempotency reservations are crash-safe — claim token + fenced completion, guarded
  reclamation, a same-transaction commit marker, and a no-re-execute recovery policy (§6.6).
- B4: customer addresses settled as global data with user scoping only; model inheritance, table
  classification, and the security gate aligned, with three distinct authorization rules (§2.1,
  §4.4, §8 Phase 8).
- B5: the domain lifecycle is persisted (`verification_token_hash`, method, expiry, release,
  cooling-off), one-primary-domain-per-tenant is a partial unique index, and verification is
  HTTPS-first with SSRF protections (§4.1, §9.7).
- F1–F6: platform-settings read path (§6.7), same-key media serialization (§6.4), search
  stale-write guarantees tied to the existing fencing (§6.8), BIGINT terminology and the explicit
  orphan-tenant-id trade-off (§4.2–§4.3), one route-audience grammar with fail-closed
  unclassified routes (§3.1), and the order-code lookup contract (§6.9).
- P1–P5: the review recommends accepting all five, with conditions recorded in §0; owner
  confirmation is still required before the phase that implements each.
- Matrix 68 → 82 rows (§10); Phase 2's schema-dump comparison is now a hard gate (§8).

Rev 4.4 changes from rev 4.3 (architecture untouched; per the second review):
- B1: tenant identification is a **two-stage lifecycle** — a pre-routing global normalizer
  (headers/Host only) and a post-routing `ResolveTenant` in the `api` group whose position is
  fixed by the middleware **priority list**, guaranteeing context before route binding and
  credential application; the principal (sanctum guard) may be read for identification only
  (§3.1).
- B2: machine tokens carry a nullable `tenant_id` on `personal_access_tokens` (NULL for ordinary
  user tokens) and are resolved in Stage 2 before binding (§3.1, §3.2, §4.4, §9.7).
- B3: the root-folder partial unique index includes `tenant_id` — `(tenant_id, name) WHERE
  parent_id IS NULL` (§4.5).
- B4: the idempotency commit marker aborts the business transaction when claim ownership was lost
  (exactly-one-row update, throw on zero); external provider idempotency keys derive from the
  stable logical identity, never the claim token (§6.6).
- B5: domain verification pins the connection to a validated IP (SNI/Host preserved) and checks
  the actual peer — DNS rebinding closed (§9.7).
- F1–F5: database-enforced default-currency and default-tax-class invariants and base-currency
  ownership reconciliation (§4.5, §6.7.1); a fenced database lease for media per-key operations
  (§6.4); the explicit provisioning write contract (§6.5); product tenant-ownership immutability
  (§6.8); the platform-settings resolver contract, incl. missing-setting behaviour (§6.7).
- Matrix 82 → 94 rows (§10).

Rev 4.5 changes from rev 4.4 (architecture untouched; per the third review):
- B1: a tenant-neutral **`onboarding` route audience** — no tenant resolution, MODE_INACTIVE
  throughout, global-user authentication, allowlisted routes only (§3.1, §6.5).
- B2: the payment-webhook `{tenant-slug}` route parameter is an authoritative Stage-2 resolution
  source; tenant headers ignored on that audience (§3.1, §6.3).
- B3: idempotency reclamation requires `committed_at IS NULL` — committed claims are never
  reclaimed; response completion moves from `terminate()` into `handle()`; commit-marker
  participation is mandatory and architecture-tested (§6.6).
- B4: `CentralOperations` takes typed operation objects resolved through a handler registry —
  the caller never supplies the closure; per-operation call-site allowlist + actor policy (§6.5).
- B5: `media_object_locks` joins the schema classification (§4.4) and deletion becomes a durable
  intent state machine with a transition-guarded unlink (§6.4).
- B6: tenant-ownership immutability enforced at four layers, incl. a database trigger and
  mass-assignment removal (§2, §6.8).
- B7: the middleware priority list preserves Laravel's defaults verbatim and orders
  `ResolveTenant` ahead of tenant-aware throttling too (§3.1).
- B8: the super-admin bypass and `adminUserIds()` constrain the polymorphic `model_type` and
  guard name (§5).
- F1–F7: bulk-write wrong-tenant guard + per-query allowlist (§4.3); Tenant-module read
  isolation (§2.1); default lifecycle guards (§6.7.1); onboarding quota serialization + the
  last-owner invariant (§6.5); the staff branch of the order-code lookup (§6.9); machine-token
  lifecycle rules + the F23 amendment (§1, §9.7); platform-settings cache invalidation (§6.7).
- Matrix 94 → 110 rows (§10); `media_object_locks` makes it 92 store-owned tables (§4.4).

Rev 4.6 changes from rev 4.5 (architecture untouched; per the fourth review):
- B1: the media unlink is fenced by an **open row-lock transaction spanning the filesystem
  call** — the critical section is the held lock, not the intent transition; lease reclamation
  and the unlink are mutually exclusive; crash-while-held recovery defined (§6.4).
- B2: the immutability trigger is specified **per engine** (PostgreSQL trigger function;
  SQLite per-table trigger bodies) behind explicit driver branches, installed on the tables
  that actually carry `tenant_id`; the nine pivots get their own documented strategy (§6.8).
- B3: **every** claim-deletion path — including `terminate()` failure cleanup — requires
  `committed_at IS NULL`; a committed claim on a response-persist failure becomes
  `idempotency_unresolved`, never a clean retry; reclamation's delete-and-reinsert concurrency
  is documented (§6.6).
- B4: onboarding is the **`onboarding` audience everywhere** — one canonical route
  (`POST /api/v1/onboarding/tenants`, `api.tenant.onboarding.*`); all stale `user`-audience
  wording replaced (§4.1, §6.5, §9.7, §8).
- F1: the query-builder allowlist is per query site, not per file (§4.3).
- F2: the wrong-tenant bulk-insert guard has a concrete mechanism — a Core stamping helper the
  architecture test verifies per registered site; caller input never carries `tenant_id` (§4.3).
- F3: the central-operation registry binds **exact final operation classes**; `id()` is
  display-only (§6.5).
- F4: the deletion-intent lifecycle is a classified table (`media_deletion_intents`) with
  statuses, permitted transitions, idempotent sweep, and publication serialization (§4.4, §6.4).
- Matrix 110 → 116 rows (§10); `media_deletion_intents` makes it 93 store-owned tables (§4.4).

Rev 4.7 changes from rev 4.6 (architecture untouched; per the fifth review):
- B1: the media unlink becomes **rename-then-unlink** — the destructive-to-live-namespace step
  (rename into a per-tenant trash path) stays inside the row-lock critical section; only the
  idempotent unlink of trash files happens outside it. The database-session-loss race is narrowed
  and its bounded, recoverable worst case is documented, with a connection-drop test (§6.4).
- B2: a queue payload can no longer grant MODE_CENTRAL. `JobProcessing` initializes only
  INACTIVE/TENANT; central-dispatched jobs carry the **registered operation id** and must invoke
  it through `CentralOperations::run()` themselves — one enforceable entry point, queue included
  (§6.1).
- B4/F3: `media:outbox:sweep` is reclassified from `platform` to **`central`** with its touched
  tables enumerated — it scans store-owned coordination rows and never tenant business data
  (§6.7.2).
- B5: the inventory is reconciled explicitly — 130 existing = 91 store-owned + 39
  installation-level; tenancy adds 7 tables (5 Tenant-module + 2 media coordination); the §4.4
  93/39 classification = 130 existing + the 2 media tables; the Tenant-module tables are
  classified separately in §4.1 (§4.4).
- B6: every mutation of a tenant's active-owner set serializes on the tenant row (`FOR UPDATE`
  before check-and-change) — removal, suspension, demotion, transfer; competing-connection test
  (§6.5).
- B7: the provisioning lifecycle is explicit — unscoped metadata writes first, then
  `runForTenant($newTenant)` for the owner membership, role attach, and tenant-owned defaults,
  restoring MODE_INACTIVE in `finally`; team id set and restored (§6.5).
- F1: the payment resolver cache identity is **per (tenant, driver)** — `credential_version`
  lives on each driver row, bumped with that driver's write; cache key
  `t{id}:payment.credentials:{driver}:{credential_version}` (§6.3).
- F2: the pivot threat-model boundary is explicit — preventive for application writes through
  the approved APIs; allowlist-covered for direct SQL in app code; **detective only** (`tenancy:
  audit`) for SQL issued outside the application (§6.8).
- Matrix 116 → 119 rows (§10).

Rev 4.8 changes from rev 4.7 (architecture untouched; per the sixth review):
- B1: the media object lifecycle is **generation-safe** — physical keys are immutable and
  unique per publication (`{sha256}-{ulid}`), references point at an exact object generation,
  keys with any deletion history are retired forever, and a deletion intent names its exact key:
  a stale worker resuming after DB-session loss can only touch its retired generation, never a
  newer publication's object (§6.4).
- B2: `media:outbox:sweep` becomes **central discovery + tenant-scoped execution** — stage 1 is
  a read-only registered operation returning `(tenant_id, record id)` work items; stage 2 runs
  each cleanup under `runForTenant`, reloading and validating the record before any write; a
  globally scheduled command never writes store-owned rows directly (§6.7.2).
- F1: trigger acceptance is split by phase — Phase 2 verifies the tables it creates, Phase 4 the
  two media coordination tables, Phase 8 the full inventory — against an explicit expected-table
  list that excludes installation-level tenant-**key** tables (§10 row 113).
- F2: the §4.4 installation-level heading now says "no store-ownership scope" and distinguishes
  tenant-key columns from store-owned data columns.
- F3: the trash root is explicitly outside the publicly served media namespace — not URL
 -addressable, cleanup-process-only (§6.4, row 121).
- Matrix 119 → 121 rows (§10; row 117 rewritten, row 113 re-phased).

Rev 4.9 changes from rev 4.8 (clarifications only, per the seventh review):
- B1 (durable retirement): retirement lives in a **persistent object-generation record** —
  `media_object_generations`, one row per physical key a store has ever published, marked
  `retired` when a deletion intent first targets it. Cancelling an intent never un-retires;
  pruning intents never touches generation rows; reuse eligibility consults the generation
  record, never intent history (§6.4, §4.4).
- B1 (wording): **physical object creation** (a new immutable generation + key) is distinguished
  from **logical media publication/reference** (may point at an existing *eligible* generation);
  the six reviewer-listed invariants are stated explicitly (§6.4).
- F3 (trash root): the physical trash location is `{disk}://trash/{tenant_id}/…` — a sibling of
  the public `media/` tree, outside the served root and the disk's URL mapping; row 121 tests
  both direct HTTP and URL-generator behaviour (§6.4).
- B2 (stage-2 details): discovery results are hints, not authorization; every item reloads,
  ownership-matches, and revalidates under `runForTenant`; stale items are safe no-ops;
  filesystem targets come only from the validated record's exact key; per-tenant failure
  isolation; `discovery+fan-out` stays narrowly registered (§6.7.2).
- Matrix 121 → 122 rows (row 122: cancelled intent + pruned history + identical re-upload → the
  retired key is never reused, and an eligible no-history object still is).
- Inventory: `media_object_generations` makes it 94 store-owned / 138 total (§4.4).

Rev 4.10 changes from rev 4.9 (per the eighth review — one blocker + two details):
- B1 (blocker): the **generation row is the common serialization point** — reference
  attachment/reuse locks the `media_object_generations` row `FOR UPDATE`, verifies `eligible`,
  and holds the lock through the reference-insert transaction; deletion-intent creation locks
  the same row, verifies reference state, marks `retired`, and writes the intent in one
  transaction; retirement is a one-way conditional update guarded by an engine-branched trigger;
  lock ordering is fixed — generation row first, media reference rows second, on every path
  (§6.4).
- Trash root: trash lives on a **dedicated private disk** with no public web-server mapping at
  all — not a sibling directory under a served root (§6.4).
- Matrix 122 → 123: row 123 is the deterministic two-connection PostgreSQL attach-vs-retire
  race, both orderings (§10).

---

## 0. Owner decisions and proposals to confirm

**Confirmed by the owner (2026-11-24):**

- A3. **Store admins (merchants) see only their own store.** Enforced by `tenant_memberships` +
  tenant roles (§3.2, §5).
- A4. **Only the platform super-admin operates across stores** — they can open any store (§3.1
  `X-Tenant-Id`) and use the platform screens (tenant CRUD, domains, extensions). There is **no
  merged cross-store view**: every cross-store read happens inside a registered `CentralOperations`
  operation (§6.5).
- A5. **An owner of several stores works in one store at a time** and switches between their own
  stores. No combined listing across a person's stores.
- A6. **The tenant filter is switched on by request, on one model per table.** The alternative —
  per-module model subclasses that carry their own global scope and are used only from
  store-facing code (the `Vendor/` pattern) — is declined; reasons in §2.1.

A1 and A2 (§1) remain in force.

**Proposals — recommended defaults, each to be confirmed before the phase that implements it:**

| # | Proposal | Phase |
|---|---|---|
| P1 | **One shared search index** with a `tenant_id` field on every document and a filter that the search client always injects. Alternative: one Meilisearch index per store (stronger isolation, more moving parts) — §6.8. | 5 |
| P2 | **One platform base currency** (all stored amounts), each store chooses its default display currency and which currencies it offers. Exchange rates stay platform-wide. Alternative: a base currency per store (conversion and rounding on every stored amount) — §6.7. | 2 |
| P3 | **Extensions are platform-wide**: installed and toggled by the super-admin only, no per-store switch. | 7 |
| P4 | **Media files are stored per store** (`media/{tenant_id}/…`), giving up cross-store deduplication so deleting a file can never remove another store's object — §6.4. | 4 |
| P5 | **Settings split** into platform-wide (FX provider + API key, platform identity) and per-store (everything that brands or configures a storefront) — §4.5, §6.7. | 2 |

Review verdict on P1–P5 (2026-11-24, external): **accept all five, with conditions.** P1 only with
the centrally enforced, non-optional tenant filter (§6.8 already makes it so); P2 with
transaction-time currency snapshots preserved on monetary rows (§6.7.1); P4 with the same-tenant
key race resolved first (§6.4); P5 with the explicit platform-settings read path (§6.7). Owner
confirmation remains the gate; the plan is written so each proposal can still be flipped
individually before its phase.

---

## 1. Confirmed codebase facts (verified) vs assumptions

**Verified facts** (file-checked):

- F1. No tenancy exists anywhere: no `tenant_id`/`store_id`/`team_id` columns; greps hit only a
  comment in `Modules/Order/app/Support/StoreTimezone.php` anticipating this change. (rev 4.2:
  re-checked after the PostgreSQL migration — still true for all 130 tables.)
- F2. Eloquent scoping precedent: `Modules/Core/app/Models/Scopes/UserScope.php` +
  `Traits/UserScopeTrait.php` (global scope + `creating` hook). Correction (rev 4, per review):
  `where('user_id', Auth::id())` with a null id compiles to `WHERE user_id IS NULL`, **not** an
  unscoped query — so it cannot expose other users' rows; the residual risk is limited to rows with
  a null `user_id` (none exist today because the `creating` hook requires an authenticated user).
  The new TenantScope still deliberately differs: a missing context throws instead of silently
  narrowing to `IS NULL`.
- F3. Middleware chokepoint: `CoreServiceProvider::boot()` pushes onto the `api` group
  (`Modules/Core/app/Providers/CoreServiceProvider.php:38`). `bootstrap/app.php` `withMiddleware`
  is empty. **Update (rev 5, RoadRunner rollout): the API is now served by Laravel Octane +
  RoadRunner with persistent workers** — the "no Octane" assumption below is superseded. Octane
  flushes most framework state per request, but it does **not** disconnect database connections
  by default (`DisconnectFromDatabases` is commented out in the published `config/octane.php`),
  and application singletons live across requests. The tenancy implementation must therefore
  treat persistent-worker compatibility as a prerequisite: `TenantContext` has to be a scoped
  binding (or carry an explicit, exception-safe reset to `MODE_INACTIVE` at the very start of
  every request), and the isolation tests in the RoadRunner rollout plan (same-worker
  consecutive requests) must pass for tenant scenarios before tenancy ships.
  (rev 4.2: decided — the reset is a `RequestReceived` listener placed first, see §2 and F39.)
  **Update (rev 6, 2026-11-26: reverting to php-fpm — the rev-5 requirements are withdrawn.**
  The Octane/RoadRunner runtime was removed (branch `revert/php-fpm`, merged to `main`); the API
  boots per request again, so the persistent-worker prerequisites above no longer apply: the
  `ResetTenantContext` Octane listener (§2; it was §6.7 item 2 before this sweep), the Octane isolation-gate tenant
  scenarios, risk R6, defect K8, and test rows 43/44/109 are dropped. Two things survive the
  revert because they are about processes that outlive a request regardless of the HTTP server:
  `TenantContext` still needs an explicit reset — Pest runs the whole suite in one process (tests
  reset in `TestCase::setUp`) — and the per-job tenant snapshot/restore for queue workers (§6.1)
  stands unchanged. The per-request store runtime after `ResolveTenant` (§6.7 item 1) is also
  still required: the tenant is only known after boot, under any server.
- F4. `PermissionMiddleware` (`Modules/Core/app/Http/Middleware/PermissionMiddleware.php`) is already
  fail-closed: no auth → throw; unnamed/non-admin route name → throw; delegates to
  `AccessGatewayInterface::userCan()` = `$user->can($permission)` with PermissionDoesNotExist → false.
- F5. Super-admin bypass: `Gate::before` in `Modules/Auth/app/Providers/AuthServiceProvider.php:39-41`
  checks `$user->hasRole(Role::SUPER_ADMIN)`. With spatie teams enabled, `hasRole` filters by the
  current team id — the bypass would silently break unless changed (handled in §5).
- F6. spatie config (`config/permission.php`): `teams => false`, `team_foreign_key => 'team_id'`,
  custom tables `auth_roles`/`auth_permissions`/`auth_model_has_roles`/`auth_model_has_permissions`/
  `auth_role_has_permissions`, wildcard permissions enabled, single permission cache
  (`spatie.permission.cache`, 24 h, auto-flushed on role/permission writes).
- F7. **Migration execution order is global by filename timestamp across all modules**
  (`Migrator` sorts all registered paths by migration name), not module priority (all `module.json`
  priorities are 0). Cross-module FK constraints would therefore be order-fragile — and cross-module
  table references violate the backend AGENTS.md module-boundary rule anyway. (rev 4.2: PostgreSQL
  also caps identifiers at 63 bytes — F30.)
- F8. Pre-v1 migration policy is explicit: `apps/backend/AGENTS.md` ("Database & Migrations"):
  edit original migrations in place; "No release has shipped yet". Root `DatabaseSeeder` is empty;
  dev DBs are disposable (`docker compose`, `migrate:fresh` sanctioned; compose `app` is sole
  migration owner via `RUN_MIGRATIONS=1`).
- F9. Raw queries that would **bypass** an Eloquent tenant scope:
  `Modules/Core/app/Rules/LanguageUniquePair.php:36`, `Modules/Core/app/Rules/UniquePair.php:28`,
  `Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php:742,882,910,933`,
  `Modules/Catalog/app/Support/AppliesProductPromotions.php:91`,
  `Modules/Catalog/app/Support/ProductImport/ProductImportOptionResolver.php:225`,
  `Modules/Content/app/Transfer/ContentTransfer.php:638,678`,
  `Modules/Discount/app/Actions/Admin/Promotion/ListPromotionAction.php:54,59`,
  `Modules/Discount/app/Gateways/PromotionGateway.php:96,101`,
  `Modules/Discount/app/Models/Promotion.php:62-100` (pivot syncs),
  `Modules/Webhooks/app/Console/Commands/PruneWebhooksCommand.php:25-35`,
  `Modules/Webhooks/app/Jobs/DeliverWebhookJob.php:170`.
  No `DB::select`/`withoutGlobalScopes` anywhere. `selectRaw` usages sit on Eloquent builders (scoped).
  (rev 4.2: this is the raw-SQL subset only. The complete surface a scope cannot see — 129
  query-builder chains, bulk writes, validation rules — is F33.)
- F10. Queued work: 22 `ShouldQueue` classes (1 `Webhooks/Jobs/DeliverWebhookJob`, 1 Media listener,
  20 Notification listeners). None carry any store/tenant context today (verified: `DeliverWebhookJob`
  takes only `$deliveryId`; outbox tables have no tenant column). (rev 4.2: now 28 classes, F36.)
- F11. Tenant-varying caches (complete list): `catalog.public.sitemap`, `content.public.sitemap`,
  `core.language.default_id`, `setting.timezone`, `setting.prices_include_tax`,
  **`payment.paypal.access_token`** (reclassified in rev 4: per-tenant under A2 — the token belongs
  to whichever tenant's PayPal credentials produced it, §6.3); invalidation only in
  `Modules/Setting/app/Actions/Admin/Setting/UpdateSettingAction.php:26-27`. Remaining global
  caches: `telegram:bot:username` (platform-level service, §9 R1). (rev 4.2: the full inventory is
  18 cache-key families — F37.)
- F12. Payment webhooks identify nothing tenant-ish today: `POST /webhook/{driver}` →
  `HandleWebhookAction` matches `payments.transaction_id` globally against driver-verified payload,
  using single global gateway credentials/secrets per driver.
- F13. Media: `$file->store()` with no path (`CreateMediaAction.php:35`) + `hashName()` — collision-safe
  names, shared root namespace, no per-tenant directory. (rev 4.2: names are now content-addressed
  — `media/{ab}/{sha256}.{ext}` via the publication outbox — but the namespace is still shared
  across stores; full picture in F45.)
- F14. Exists-checks crossing modules (must become tenant-scoped):
  `CatalogGateway::{productExists,categoryExists,variantExists}`, `TaxGateway::taxClassExists`, and
  callers in Catalog/Cart/Content/Discount/Inventory/User requests + Transfer import paths.
- F15. Tests send no host/tenant headers (`baseUrl()` is a relative path; only `Accept` headers appear);
  `phpunit.xml` sets no server defaults (`QUEUE_CONNECTION=sync`, `DB_CONNECTION=sqlite`,
  `CACHE_STORE=array` — phpunit.xml:33-37). Middleware fallback behavior determines whether the
  existing suite stays green.
- F16. `IdempotencyMiddleware` keys replays by `user_id + key` only — cross-tenant replay is possible
  for multi-tenant staff, and the check-execute-store sequence (`handle()` → action → `terminate()`)
  has a concurrency window: two parallel requests with the same key both execute (§6.6, F26).
- F17. Admin SPA: axios chokepoint `apps/admin/src/shared/lib/api-client.ts`, placeholder switcher
  `apps/admin/src/components/layout/team-switcher.tsx`. Storefront: `apps/storefront/proxy.ts`
  (next-intl only), `apps/storefront/lib/api/client.ts` (browser calls are cross-origin, so the API
  sees the API host, not the storefront host — hence a forwarded header is required, §7).
- F18. Spatie Laravel Permission **8.3.0** vendor stub
  (`vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub`)
  defines teams schema semantics: `auth_roles.tenant_id` is nullable and its unique key is
  `[tenant_id, name, guard_name]`; `auth_model_has_roles.tenant_id` and
  `auth_model_has_permissions.tenant_id` are **NOT NULL** and lead their composite primary keys.
  NULL on the role row is the package's global-role mechanism; pivot team values are never NULL.
  The app migration `Modules/Auth/database/migrations/2026_09_08_153239_create_permission_tables.php`
  is already a verbatim copy of the vendor stub (its roles unique is chosen by `$teams ||
  config('permission.testing')`, lines 47–51).
- F19. Laravel framework source (`vendor/laravel/framework/src/Illuminate/Queue/Worker.php:615-662`)
  fires `JobAttempted` in the worker's `finally` block after every attempt, including success,
  retry release, and terminal failure.
- F20. Module-boundary convention: `Modules/Content/database/migrations/2026_10_10_000001_create_post_product_table.php:20-23`
  explicitly says cross-module tables do not receive foreign keys; ids are validated through gateways.
  Existing `user_id` references in orders, carts, support tickets, profiles, and wishlist items are
  plain indexed columns. `payment_refunds.order_id` is the sole existing cross-module FK and is
  treated as pre-existing debt, not precedent.
- F21. Audit mechanics: `Modules/Audit/app/Providers/EventServiceProvider.php` maps ~40 admin-mutation
  events to `RecordAuditLogListener` (module/entity/action/entity_id/properties/ip/user_agent,
  sensitive keys redacted); tenancy lifecycle events join this existing event-driven audit path.
- F22. Webhook dispatch paths: scheduled console dispatch (`webhooks:dispatch-deliveries`, every
  minute, `WebhooksServiceProvider`), console retry command, admin HTTP retry route
  (`Modules/Webhooks/routes/api/admin.php:27`, behind `auth:sanctum` + `PermissionMiddleware`),
  and listener-driven dispatch from domain events. `DeliverWebhookJob` receives a delivery id and
  needs an explicit bootstrap lookup before tenant-scoped delivery work (§6.2).
- F23. Sanctum personal access tokens remain global user credentials; tenant selection and access
  authorization are resolved per request. No tenant claim is embedded in the token. (rev 4.5, F6
  amendment: that holds for **ordinary user tokens**; **machine tokens** are a distinct issuance
  that carries `tenant_id` on the token row — §9.7, B2 — immutable after issuance.)
- F24. Owner decisions: A1 (no production data to preserve) and A2 (per-tenant payment gateway
  credentials from day one) are confirmed, not assumptions.
- F25. Payment configuration read-sites (complete inventory for the §6.3 refactor): gateway clients
  `StripeCheckout.php:38`, `PaypalClient.php:64,80-81,100`, `CheckoutcomClient.php:50,66,81`,
  `NowpaymentsClient.php:33,41,55`, `AdyenCheckout.php:54,63,79`; drivers `StripePaymentDriver.php:58,68`,
  `AdyenPaymentDriver.php:24,35,62,166,194` (incl. `return_url`), `NowpaymentsPaymentDriver.php:39,64`,
  `CheckoutcomPaymentDriver.php:54`, plus `RazorpayPaymentDriver`, `MercadopagoPaymentDriver`,
  `ManualPaymentDriver`; availability gating in `PaymentDriverManager.php:58-71` reads global config
  for all eight methods.
- F26. `Modules/Core/database/migrations/2026_03_25_092852_create_idempotency_keys_table.php`:
  `KEY` is a **globally unique** string, `USER_ID` nullable, no `tenant_id`. Combined with F16: adding
  `tenant_id` without replacing the unique index would (a) still allow the check-execute-store race
  and (b) prevent two tenants from using the same key string at all.
- F27. **Sync queue driver raises the same lifecycle events as the worker** — verified
  `vendor/laravel/framework/src/Illuminate/Queue/Events/` imports and
  `SyncQueue.php:224-261`: `raiseBeforeJobEvent` → job → `raiseAfterJobEvent`, with
  `raiseJobAttemptedEvent` in the `finally` (carrying the exception if any). The review's concern
  that sync dispatch bypasses `JobProcessing`/`JobAttempted` does not hold on this framework
  version — but it exposed a real rev-3 design flaw: under sync dispatch the job runs **inside the
  request**, so a naive "deactivate on JobAttempted" would wipe the request's tenant context
  mid-request. The rev-4 lifecycle uses snapshot/restore semantics instead (§6.1), and both
  execution models get explicit tests (review requirement kept).

**Facts added in rev 4.2 (inventory of 2026-11-24, commit `1305e55b`; counts are from greps over
`apps/backend`, the file references were read directly):**

- F28. Schema size: 130 tables in 100 migration files (6 framework/app-level, 124 module). No
  tenant column anywhere.
- F29. Keys: 9 composite primary keys; `catalog_variant_option_values` has no primary key at all.
  71 foreign keys — 70 intra-module, 1 cross-module (`payment_refunds.order_id → order_orders`).
  66 plain cross-module id columns carry no FK (24 → `auth_users`, 17 → `core_languages`,
  5 → `media_media`, 4 each → `catalog_variants` / `tax_classes`, 3 each → `catalog_products` /
  `order_orders`, …) — the F20 convention, applied consistently.
- F30. Unique indexes: 90 — 71 auto-named, 19 explicit (5 of them raw `CREATE UNIQUE INDEX`
  statements for case-insensitive keys: user email, variant SKU, variant barcode, newsletter email,
  coupon code). 22 contain a nullable column. Three auto-generated names are exactly 63 characters
  (`catalog_variant_option_values_…_unique`, `catalog_featured_items_…_unique`,
  `content_featured_items_…_unique`) — PostgreSQL's identifier limit — so any index that gains a
  column needs an explicit short name.
- F31. 17 translation tables keyed on `language_id` (9 of them allow a NULL language): 9 Catalog
  (attribute, attribute_value, attribute_group, brand, category, option, option_value, product,
  product_seo), 4 Content (page, post, post_seo, post_category), 2 Menu (menu, menu_link),
  1 Setting, 1 Shipping (method). Each has an `[owner_id, language_id]` unique; 8 also have a
  `[language_id, slug]` unique (brand, category, option, option_value, product, page, post,
  post_category).
- F32. Models: 104 Eloquent models, no shared base class. Exactly one global scope exists
  (`UserScope`, F2). 28 tables have no application model. 5 models override `boot()` (all call
  `parent::boot()`); 2 override `booted()` (`Modules/Webhooks/app/Models/WebhookDelivery.php:76`,
  `WebhookEvent.php:55`). A subclass-with-scope model already exists for the *user* dimension:
  `Modules/User/app/Models/User/Address.php`
  (`class Address extends \Modules\User\Models\Address { use UserScopeTrait; }`).
- F33. Surface an Eloquent scope cannot see: 129 query-builder chains (119 `DB::table`, 10
  connection-level) — Catalog 58 (50 on the search-coordination tables, which are
  installation-level), Order 14, Discount 14, Media 10, Auth 8, Payment 8, Core 7, Support 5,
  Webhooks 3, Content 2. Also 5 raw statements, 24 raw SQL fragments, 81 Eloquent bulk writes
  (`insert` / `upsert` / builder `update` — no model events), 12 pivot `attach`/`sync` calls, and
  48 framework `unique:` / `exists:` validation sites.
- F34. Routes: 409 module routes — admin 296, user 63, public 44, webhook 2, machine 4. The
  audience is the third segment of the route name (`api.{module}.{area}.*`); exceptions:
  `api.notification.webpush.*`, `api.notification.telegram.*`, and the unnamed `/api/user`.
- F35. Effective `api` middleware order today: `ApplyDatabaseCredentials` → `SubstituteBindings` →
  `AssignRequestId` → `SetSecurityHeaders` → [`RequestLogger`]. `ApplyDatabaseCredentials` is
  prepended from `Modules/Payment/app/Providers/PaymentServiceProvider.php:67-73` inside an
  `app->booted()` callback: it runs before any tenant can be resolved and before route-model
  binding.
- F36. Async work: 28 `ShouldQueue` classes (Catalog 2, Media 1, Notification 24, Webhooks 1), all
  listed in `Modules/Core/config/dispatch.php`; `NotificationCreated` (`ShouldBroadcast`) is
  outside that registry. 13 scheduled commands: `sanctum:prune-expired`,
  `auth:prune-sign-in-lockouts`, `catalog:search:reconcile`, `core:queue-check-backlog`,
  `queue:prune-failed`, `currencies:sync-rates`, `media:outbox:process`, `media:outbox:sweep`,
  `order:cancel-expired`, `order:sweep-generating-documents`,
  `order:reconcile-refund-credit-notes`, `webhooks:dispatch-deliveries`, `webhooks:prune`.
- F37. Caches, locks, limiters — none is tenant-aware. 18 cache-key families (16 named here; the
  complete list is a Phase 4 entry artifact). Store-dependent: `setting.timezone`,
  `setting.store_identity`, `setting.fx`, `setting.prices_include_tax`, `payment.credentials`,
  `payment.paypal.access_token`, `core.language.default_id`, `catalog.public.sitemap`,
  `content.public.sitemap`. Installation-level by nature: `telegram:bot:username`,
  `core.extension-updates` (+ `.stale`), `core.regions.{CC}`, `catalog.search.reconcile.failures`,
  the Google JWKS cache. 6 cache locks: `sign-in-gate:{hash}`, `media-variants:{id}`, the
  variant-generation lock, `core.fx.sync`, `webhook-endpoint:{id}`, the Google JWKS refresh.
  12 rate limiters: `auth-sign-in`, `auth-sign-up`, `auth-otp-request`, `auth-otp-verify`,
  `cart-public`, `contact-submit`, `contact-subscribe`, `notification-webhook`,
  `support-ticket-create`, `support-ticket-reply`, `webhook`, `machine`.
- F38. Swallowed failures: seven places catch `Throwable` and fall back to a default while resolving
  store-dependent values — `Modules/Setting/app/Support/AppliesStoreTimezone.php:32`,
  `AppliesStoreIdentity.php:38`, `ResolvesPricingMode.php:26`, `ResolvesFxSettings.php:42,64,99`,
  `Modules/Core/app/Support/SupportedLocales.php:44`,
  `Modules/Payment/app/Support/ResolvesPaymentCredentials.php:50,83,117` (this one falls back to
  the `.env` baseline), `Modules/Payment/app/Http/Middleware/ApplyDatabaseCredentials.php:62`.
- F39. Store runtime is applied per process, not per request:
  `Modules/Setting/app/Providers/SettingServiceProvider.php:34-45` runs `AppliesStoreTimezone` and
  `AppliesStoreIdentity` once, in `boot()`. The timezone is set with `date_default_timezone_set()`
  (process-global). Statics hold request-derived state: `SupportedLocales::$codes`,
  `ResolvesPaymentCredentials::$baseline`, `SearchMutationGate::$depth`,
  `SearchCursorManager::$held`. `config/octane.php` has `'flush' => []`; its `RequestReceived`
  listeners are `FlushSentryScope` and `FlushExtensionState`, and nothing resets store state
  between requests. (rev 6: withdrawn with the Octane runtime — under php-fpm `boot()` runs per
  request, so the per-process staleness F39 described no longer exists for HTTP; it remains true
  for `queue:work`, which §6.7 item 3 covers.)
- F40. Search: `catalog_search_gate` is a single row (`id = 1`); the index uid is `{uid_base}_g{n}`;
  documents carry no tenant field (their only store-dependent values are base-currency prices,
  minor units, precision 2); the primary key is `product_id`, which stays installation-unique
  because ids come from one database sequence. Queue payloads carry only the product id.
  `Modules/Catalog/app/Search/CatalogSearchEngine.php:60-100` hydrates hits through
  `PublicProductQuery::hydrationQuery()` (Eloquent — scope-compatible) but returns `total`
  straight from the engine. `catalog:reindex` — called the nightly backstop in the operations
  runbook — is neither registered as a command nor scheduled; `catalog:search:install`,
  `catalog:search:cleanup` and `media:regenerate-variants` are unregistered too.
- F41. Order documents: `Modules/Order/app/Support/DocumentNumberService.php` numbers per
  `(type, year)` installation-wide (`document_sequences` unique `[type, year]`); the year comes
  from `StoreTimezone::year()` (the process timezone); the prefix from
  `config("order.documents.prefixes.{type}")`; seller identity from `config('store.*')`; PDFs are
  written to `documents/{number}.pdf` on the `local` disk; `order:cancel-expired`,
  `order:sweep-generating-documents` and `order:reconcile-refund-credit-notes` sweep globally.
- F42. Payments: `payment_driver_credentials` (`UNIQUE(driver, key)`) is read by
  `ResolvesPaymentCredentials` in the order stored value → `.env` baseline; the stored map is
  cached under `payment.credentials` for 15 minutes with no tenant in the key;
  `ApplyDatabaseCredentials` copies the map into `config("payment.{driver}.{key}")`;
  `POST /webhook/{driver}` looks up `payments.transaction_id` globally; return URLs come from env
  and hard-code `/en/`.
- F43. Transfer: `transfer_installation` is a single row (`FIXED_ID = 1`); `transfer_remaps` is
  unique on `[source_installation, module, entity, source_id]` with no destination tenant; the
  import lock is `transfer:import:{module}`; `SettingTransfer` exports every setting including
  store identity and the `fx_api_key` ciphertext; `Modules/Discount/app/Transfer/DiscountTransfer.php:537-539`
  updates every coupon row with no `WHERE`; exports are streamed to the response, not stored.
- F44. Extensions: `core_extensions` is installation-level, but the toggle routes sit under
  tenant-admin permissions (`core.admin.extensions.*`).
- F45. Media: keys are content-addressed (`media/{ab}/{sha256}.{ext}`), so identical files share
  one object across stores; `DeleteMediaAction` deletes files unconditionally; staging files live
  in `.staging/media/{uuid}.{ext}`; `media_media_outbox` rows have no tenant;
  `MediaGateway::upsertLocal` hard-codes `USER_ID => 1`; stored `catalog_images.url` values embed
  the installation host.
- F46. Settings and reference data: `setting_settings.name` is unique and holds store identity,
  `language_id`, `currency_id`, `timezone`, `storefront_theme`, `prices_include_tax`,
  `store_legal_*`, `fx_*`; `core_languages` / `core_currencies` carry store-level `is_default` /
  `is_active` flags; exchange rates live on `core_currencies`; `tax_classes.is_default` is a single
  default.
- F47. Notifications: templates read `config('app.name')`; the mail sender is env; admin recipients
  are a configured list or every staff user (`Modules/Notification/app/Support/AdminRecipients.php`);
  broadcast channels are named by user id.

**Confirmed owner decisions (gates closed):**

- A1. **Confirmed: no production database data must be preserved.** Editing original migrations in
  place is the selected path; the backfill recipe is retained only in Appendix A if this changes.
- A2. **Confirmed: payment gateway credentials are per-tenant from day one.** Phase 4 includes
  tenant-scoped encrypted gateway configuration, per-tenant webhook secrets, and the full client
  refactor of F25.

---

## 2. Core runtime — fail-closed by construction

New files in `Modules/Core/app/Support/Tenancy/`:

- **`TenantContext`** (singleton) with three explicit states — there is no implicit "no tenant = see
  everything":

  ```php
  final class TenantContext
  {
      public const MODE_INACTIVE = 0; // default; tenant-scoped queries THROW
      public const MODE_TENANT   = 1; // normal request/job: tenant filter applies
      public const MODE_CENTRAL  = 2; // explicit cross-tenant mode — entered ONLY via CentralOperations (§6.5)

      public function forTenant(int $id): void;          // @internal for middleware/CentralOperations
      public function central(): void;                   // @internal — CentralOperations only (arch-enforced)
      public function deactivate(): void;                // @internal
      public function mode(): int;
      public function tenantId(): ?int;                  // null unless MODE_TENANT
      public function tenant(): ?TenantSnapshot;         // cached name/slug/domains via TenantGatewayInterface
      public function runForTenant(int $id, callable $fn): mixed;  // try/finally restore
      public function cachePrefix(): string;             // "t{ id }:" in tenant mode
  }
  ```

  `runForTenant` snapshots and **restores the previous mode in `finally`**. The singleton lives
  for the process lifetime — under `queue:work` many jobs share one process, and Pest runs the
  whole suite in one process — so the invariant "**every request begins in `MODE_INACTIVE`**" is
  owned by identification: `ResolveTenant` resets the context as its first act (§3.1), every job
  attempt restores the snapshot its `JobProcessing` pushed (§6.1), and `TestCase::setUp` resets
  it per test — a previous request's tenant can never leak into the next one. (rev 4.2: decided
  on an Octane `RequestReceived` listener, `ResetTenantContext` — withdrawn by rev 6, the
  php-fpm revert; `ResolveTenant`'s own reset is the single owner of the invariant.) Queue
  workers are handled in §6.1. `runCentral` is **removed** from the public
  surface in rev 4 — cross-tenant execution goes through `CentralOperations` (§6.5), which is the
  only caller of the internal `central()`.

- **`TenantScope`** (`Modules/Core/app/Models/Scopes/`) — the fail-closed global scope:

  ```php
  public function apply(Builder $builder, Model $model): void
  {
      $context = app(TenantContext::class);

      if ($context->mode() === TenantContext::MODE_CENTRAL) {
          return;                       // explicit, authorized cross-tenant operation
      }

      if ($context->mode() !== TenantContext::MODE_TENANT) {
          throw new TenantContextMissingException($model);  // fail closed — never query unscoped
      }

      $builder->where($model->qualifyColumn(TenantScopedTrait::TENANT_ID), $context->tenantId());
  }
  ```

  `TenantContextMissingException` extends `Modules\Core\Exceptions\BaseException` (httpStatus 500,
  `isSafe = false` → reported, raw message hidden by the existing envelope) — a missing context is a
  programming/deployment bug, not a client error.

- **`TenantScopedTrait`** (`Modules/Core/app/Models/Traits/`) — adds `TenantScope` + sets `tenant_id`
  on `creating` from the context (throwing if inactive), mirroring `UserScopeTrait`'s shape.
  (rev 4.2 mechanics:) it registers through **`bootTenantScopedTrait()`**, Laravel's trait-boot
  hook, and does **not** define `booted()` on the trait: `WebhookDelivery` and `WebhookEvent`
  already override `booted()` (F32), and a class method silently replaces a trait method of the same
  name, so those two models would never receive the scope. The `creating` hook:
  - `MODE_TENANT` → stamp `tenant_id` from the context;
  - `MODE_CENTRAL` → stamp nothing, and allowed **only** on a model that declares a platform tier
    (`audit_logs`, `settings` — §4.4); any other model throws — a central write to a store-owned
    table without an explicit tenant is a programming error;
  - `MODE_INACTIVE` → throw `TenantContextMissingException`.
  (rev 4.5, B6 — `tenant_id` is **immutable**: the trait also registers an `updating` hook that
  throws whenever `tenant_id` is dirty, and the column is never in `$fillable`. Query-builder
  writes skip model events, so the same invariant is enforced by the §4.3 architecture allowlist
  (bulk updates may not touch `tenant_id`) and by a database trigger on each table that carries
  `tenant_id` — implemented **per engine** (§6.8, B2) — rejecting `UPDATE … SET tenant_id`
  outright (§6.8, rows 92/101).)
- **`TenantScopedTrait`** (column constants) in `Modules/Core/app/Schemas/Shared/` — exports
  `TENANT_ID = 'tenant_id'` the same way `PkAndTimestampsTrait` exports shared columns. Core is the
  sanctioned exporter of shared schema traits, so modules never reference the Tenant module's schemas
  (respects the "Schemas are module-internal" rule).
- `Modules/Core/app/Contracts/Gateways/Tenant/TenantGatewayInterface` (+ DTOs) — Core-facing contract:
  `resolveByDomain(string $host): ?TenantSnapshot`, `find(int $id): ?TenantSnapshot`,
  `defaultTenant(): ?TenantSnapshot`, `isMembershipActive(int $userId, int $tenantId): bool`,
  `membershipsFor(int $userId): array`. Implemented by `Modules/Tenant/app/Gateways/TenantGateway.php`,
  bound in `TenantServiceProvider::$bindings` (house Gateway pattern).

### 2.1 How models pick up the tenant filter (A6)

- **One model per table.** Every existing model backed by a store-owned table (§4.4) gets one
  added line: `use TenantScopedTrait;` — nothing else changes for callers. Read filter and write
  stamp are automatic in every code path, admin and storefront alike.
- **New models only for tenancy's own tables**, in the new `Tenant` module: `Tenant`,
  `TenantDomain`, `TenantMembership`, `TenantLanguage`, `TenantCurrency` (§4.1). No copies of
  existing models, no `Tenant/` model folders in other modules.
- **Tenant-module read isolation (rev 4.5, F2).** `Tenant`, `TenantDomain`, `TenantMembership`
  are metadata models without `TenantScopedTrait`; every read goes through
  `TenantGatewayInterface`, whose global lookups are deliberate and enumerated (`resolveByDomain`,
  `find`, `defaultTenant`, `isMembershipActive`, `membershipsFor` — the last is the
  tenant-selection lookup of §3.1). Member **listing** of the active tenant runs in the membership
  action constrained to the context tenant; `TenantLanguage` / `TenantCurrency` use
  `TenantScopedTrait` (store-owned) and are never read unfiltered. Test: row 104.
- **The existing subclass models stay as they are — but they differ (rev 4.3, B4).**
  `User\Ticket`'s base gets `TenantScopedTrait` (support tickets are store-owned, §4.4); the
  subclass composes `UserScopeTrait` on top, and Laravel boots traits per class, so both scopes
  register for the subclass. `User\Address` is the opposite case: the customer address book is
  **global** (§4.4), so its base model gets **no** `TenantScopedTrait` — `UserScopeTrait` stays its
  only scope. Store staff never read the global address book; what a store sees of a customer's
  address is the order-time snapshot in `order_addresses` (store-owned). The security gate
  (§8 Phase 8) therefore asserts three distinct rules: a customer reads their own book in any
  store; one customer never reads another's; staff read only their store's order-address
  snapshots.
- **Tables without a model (28, F32)** are reached through gateways and query-builder chains; they
  are covered by §4.3 layer 0, not by a model.
- **Why not per-module `Vendor/`-style subclasses** (the pattern is declined, its goal is kept):
  1. The unscoped base model stays usable everywhere, so one forgotten subclass is a silent
     cross-store read. With one scoped model, a missing context is a thrown exception.
  2. 129 query-builder chains and 48 validation rules (F33) never pass through a model subclass, so
     the subclass approach still needs the same per-site remediation — it only adds a second model
     per table.
  3. In Baxela the tenant is *the store being served* (`TenantContext`), not the authenticated
     account. The reference implementation captures the vendor in `booted()`; on a persistent
     worker that runs once, not per request (the Octane-era F39 — withdrawn by rev 6).
  4. After Phase 2 there is no legitimate code path that should see all stores except
     `CentralOperations`, so a second, unscoped model has no legitimate caller to serve.

---

## 3. Identification vs authorization — two middlewares

### 3.1 Tenant identification — a two-stage lifecycle (rev 4.4, B1)

Identification must be established before route-model binding and credential application, but the
audience decision needs the matched route's name and some paths need the principal — neither is
available to a global middleware, which runs before routing (Laravel dispatches to the router only
after the global stack). The design is therefore split:

- **Stage 1 — `NormalizeTenantRequest` (global, prepended once in `bootstrap/app.php`).** Pure
  transport-level work — no route, no database, no principal: lowercase/punycode normalization,
  port and trailing-dot stripping, IP-literal and `*.local` rejection (production mode), and
  extraction of the candidate signals (`X-Tenant-Id`, `X-Tenant-Host`, `Host`) into typed request
  attributes. It decides nothing and is inert outside future tenant-aware surfaces.
- **Stage 2 — `ResolveTenant` (first middleware of the `api` group, position fixed by the
  middleware priority list).** Group middleware runs on the **matched** route, so the route name
  and the audience grammar below are available here. `bootstrap/app.php` declares a priority
  list that **preserves Laravel's complete default ordering verbatim** (rev 4.5, B7 — the
  framework's list is extended, never replaced, so the framework's own guarantees around
  authentication, sessions, throttling and authorization keep holding) and inserts
  `ResolveTenant` ahead of `ApplyDatabaseCredentials`, `SubstituteBindings`, **and the throttling
  middleware** — tenant-aware rate limiters (§6.12) need the context too. Laravel
  sorts the route's gathered stack by that list, so the order is deterministic **whatever any
  provider prepends or pushes later** — F35's registration-order fragility disappears.
  `ResolveTenant` applies the audience rules to the Stage-1 candidates, performs the
  `tenant_domains` lookup and the fallback, sets `TenantContext`, raises `TenantResolved`, and
  404s when nothing resolves — all before any binding or credential middleware can run. It is not
  in the stack at all for non-`api` routes.

**Principal availability (rev 4.4, B1).** Two identification paths need the caller: admin default
selection (memberships) and machine-token resolution. Both read the principal inside Stage 2 via
the sanctum guard (`Auth::guard('sanctum')->user()` — an idempotent, on-demand check; invoking the
guard early is safe and consumes nothing). **Identification may read the principal; authorization
never happens here** — §3.2 remains the authorization boundary. Machine resolution parses the
bearer token, validates it against `personal_access_tokens` (hash check, not revoked, not
expired) and takes the tenant from the token row (§9.7); any token problem fails closed — a
tenant header is never a substitute.

**Order tests (rev 4.4, per review).** The order is proven **behaviorally**, not by asserting a
configured list position: a route-model-bound scoped resource resolves under the correct tenant
(binding ran after the context was set), a request that follows another store's request observes
the context reset before both stages, and a test reads the priority-sorted gathered middleware of
a real matched route. Row 83.

**Route-audience grammar (rev 4.3, F5 — the single definition; from F34).** Every module API
route is named `api.{module}.{audience}.{…rest}`: the **third segment** is the audience, taken
from the closed set `admin | user | public | webhook | machine | onboarding` (rev 4.5, B1 —
`onboarding` is the **tenant-neutral** audience: Stage 2 performs **no** tenant resolution,
`TenantContext` stays MODE_INACTIVE throughout, the global user is still authenticated, and only
routes named in a config allowlist may carry it — the route-audience architecture test fails any
other route using it, so it can never become a general bypass). Two declared exceptions map to
`public`: `api.notification.webpush.*` and `api.notification.telegram.*` (platform notification
channels). Any other `api.*` name — and any unnamed API route — is **unclassified, and
unclassified fails closed**: `ResolveTenant` returns the 404 envelope in production and throws in
non-production. A Phase 1 test enumerates the live route table and fails on any unclassified API
route, so a future module route without a proper audience segment cannot silently mis-route tenant
trust. Resolution order:

| Priority | Source | Honored for | Notes |
|---|---|---|---|
| 1 | `X-Tenant-Id` header | **admin audience only** | numeric; existence + status checked; authorization happens later (§3.2) |
| 2 | `X-Tenant-Host` header | public + user audiences only | storefront-forwarded host (§7); never honored on admin routes — a header can never override a storefront's domain identity |
| 3 | `Host` lookup in `tenant_domains` | all audiences | works when the API itself is served on a tenant domain |
| 4 | fallback host list | all | §3.3 — dev/legacy hosts only |
| 5 | — | — | **404** `core::errors.tenant_not_found` envelope |

Normalization before lookup: lowercase, strip port, strip trailing dot; reject IP-literal/`*.local`
mDNS hosts in production mode; IDN → punycode (`idn_to_ascii`).

**Webhook audience (rev 4.5, B2).** For `webhook` routes the matched route's `{tenant-slug}`
parameter is the **authoritative** source — it outranks every entry in the table above, and all
tenant headers are ignored on that audience. Unknown or suspended tenants fail closed, the
context is set before credential application and any scoped binding, and signature verification
uses the resolved tenant's secret (§6.3). Test: row 96.

**Admin default selection (rev 4.2, A5; lifecycle rev 4.4).** On a central host an admin request
without `X-Tenant-Id`: exactly one active membership → that store; several → `422
core::errors.tenant_selection_required` with the membership list; none → `403`. The super-admin
always names the store. The membership lookup runs inside Stage 2 with the principal read via the
sanctum guard (above) — identification only.

Suspended tenants: identification succeeds but the snapshot carries suspended status. Public and
storefront-user audiences receive `403 core::errors.tenant_suspended` (storefront closed); active
tenant members on admin routes receive the same 403; platform super-admins retain full admin access,
including reactivation. Queued webhook/notification jobs for a suspended tenant are skipped and
audit-logged; webhook deliveries remain pending and can be re-dispatched after reactivation.

### 3.2 `AuthorizeTenantAccess` — authorization (after `auth:sanctum`)

| Audience | Rule |
|---|---|
| `public` | none (identification only) |
| `user` (storefront customers) | any authenticated global user. **`X-Tenant-Id` is ignored** — identity came from host. Customers shop at any tenant without membership. |
| `admin` | resolved tenant must have an **active `tenant_memberships` row for the user** (§4.1), or user is platform super-admin (F5). Else **403** `core::errors.tenant_membership_required`. This is what makes `X-Tenant-Id: <someone-else>` fail closed. Membership revocation takes effect on the next request (no caching of membership beyond the request; §10 row 33). A store admin has no route to another store's data — there is no cross-store listing (A3, A4). |
| `webhook` | n/a — see §6.3 (payment webhooks resolve tenant from the route slug + per-tenant secret) |
| `machine` (rev 4.2; design rev 4.4, B2) | the tenant is the one the machine token was issued for — resolved in Stage 2 from the token's stored `tenant_id` (§3.1, §9.7); no header can change it |

Placement: `PermissionMiddleware` already terminates the admin chain; `AuthorizeTenantAccess` logic
is invoked inside `PermissionMiddleware::handle()` (single registration point, no per-module route
edits) and additionally calls `setPermissionsTeamId($tenantId)` (§5) before the permission check.
For `user` routes it runs after `auth:sanctum` (pushed to the `api` group or a shared middleware
constant — implementation picks the smaller diff; the rule itself is fixed).

### 3.3 Default-tenant fallback — restricted

`Modules/Core/config/config.php` additions:

```php
'tenancy' => [
    'fallback_hosts' => env('TENANCY_FALLBACK_HOSTS', 'localhost,127.0.0.1'), // dev default
    'fallback_tenant' => 'default',   // slug of the seeded default tenant
    'central_hosts'   => env('TENANCY_CENTRAL_HOSTS', ''), // admin API hosts: host-resolution off
],
```

Rules: an unregistered host resolves to the default tenant **only if it matches the fallback list**
(default: loopback names for local dev; production `.env.production` ships an **empty** list → unknown
domains 404). `testing` env: tests hit relative URLs with `Host: localhost` (F15) — the loopback
default keeps the entire existing suite green while isolation tests exercise the fail-closed paths
explicitly. Single-store production installs register their domain as the default tenant's
`tenant_domains` row — no fallback needed.

---

## 4. Data model & migration strategy

### 4.1 New module: `Tenant` (mirrors Shipping's structure per backend AGENTS.md)

Tables (new migrations, dated `2026_11_*`, self-contained — **no other module's migration
references them**, so F7's interleaved ordering is irrelevant):

- `tenants` — id, name, slug (unique), email, `status` enum(active|suspended), timestamps, softdeletes.
- `tenant_domains` — id, `tenant_id` FK→tenants cascade, `domain` (unique, punycode-normalized),
  `status` enum(pending|verified|released), `is_primary` bool, `verified_at` nullable timestamp,
  `verification_token_hash` nullable string (the 32-byte challenge token is stored **hashed** — it
  exists only ever to be compared), `verification_method` enum(https|dns),
  `verification_expires_at` nullable, `released_at` nullable, `cooling_off_until` nullable,
  timestamps. These columns are the persistence of the §9.7 lifecycle protocol (token expiry,
  release, cooling-off) — the procedure and the schema now say the same thing. One invariant the
  unique on `domain` cannot express — **at most one primary domain per tenant** — is
  enforced at the database level by a partial unique index,
  `tenant_domains_one_primary_unique ON (tenant_id) WHERE is_primary` (portable across PostgreSQL
  and SQLite, B5). The `domain` key itself is also partial:
  `tenant_domains_domain_unique ON (domain) WHERE status <> 'released'`, so a released row keeps
  the domain through its cooling-off window without blocking a later attachment; the window is
  enforced by the domain lifecycle actions, not by the index.
- `tenant_memberships` — id, `tenant_id` FK→tenants cascade, `user_id` plain indexed
  `unsignedBigInteger` (no cross-module FK; user existence is validated through
  `AccessGatewayInterface` / the User gateway), `status` enum(active|invited|suspended),
  `is_owner` bool, `joined_at`, timestamps, **`UNIQUE(tenant_id, user_id)`** (short index name
  per the 63-byte rule).
  Membership ≠ authorization: membership answers *belongs to*, spatie answers *may do*.
  Platform super-admin authorization derives only from the NULL-team `SUPER_ADMIN` role; it never
  derives from membership. The seeded default-tenant owner membership exists only for ownership
  semantics (member management and ownership transfer).
- `tenant_languages`, `tenant_currencies` (rev 4.2, P2) — id, `tenant_id` FK→tenants cascade, a
  plain `language_id` / `currency_id` (no cross-module FK; validated through the Core gateways),
  timestamps, `UNIQUE(tenant_id, language_id)` / `UNIQUE(tenant_id, currency_id)`. They replace the
  store-level `is_active` / `is_default` flags on `core_languages` / `core_currencies` (F46):
  reference tables become pure reference data, each store lists what it offers, and the store's
  default language / display currency are per-store settings (§6.7).

Schemas (`TenantSchema`, `TenantDomainSchema`, `TenantMembershipSchema`,
`TenantLanguageSchema`, `TenantCurrencySchema`), models, admin CRUD
Actions/Controllers/Requests/Resources under `api/v1/tenant/admin/...` (super-admin only — no
assignable permission; guarded by the same Gate::before), member-management endpoints
(list/invite/activate/suspend/remove), self-serve onboarding endpoint (**`onboarding`
audience** — the tenant-neutral route of §3.1; canonical route
`POST /api/v1/onboarding/tenants`, name `api.tenant.onboarding.tenants.store`; requires an
authenticated global user, never a resolved tenant, §9.7),
`ErrorCodeEnum` + `lang/{ar,de,en,es,fa,fr}/errors.php`, `TenantDatabaseSeeder`. (rev 4.3, B1)
Domain CRUD executes through `CentralOperations` (§6.5 Category 1); member management runs as
tenant operations (§6.5 Category 2); self-serve store creation runs through `ProvisionTenantAction`
(§6.5 Category 3) — the three categories are defined once, in §6.5.

`CoreDatabaseSeeder` seeds the **default tenant** (slug `default`) + `localhost` domain +
owner membership for the seeded super-admin — this is the "multi-tenant by default, single-store by
zero config" guarantee. The membership grants ownership semantics only; platform authorization
continues to derive exclusively from the NULL-team super-admin role.

### 4.2 Migration policy

Primary path — **edit original migrations in place** (sanctioned pre-v1 policy, F8; A1 confirmed):

1. Verified: no release has shipped; dev databases are disposable (`docker compose down -v` +
   `migrate:fresh` + reseed is the documented upgrade path).
2. Every tenant-owned table gains `tenant_id` as **plain `unsignedBigInteger` + index — no FK to
   `tenants`** (avoids cross-module migration-order fragility, F7, and respects the module-boundary
   rule that forbids referencing another module's tables). Composite uniques add `tenant_id`
   (explicit short names). Tenancy integrity is enforced at the app layer (§4.3) plus intra-module
   composite FKs (§4.3), a deliberate, documented trade-off.
3. NOT NULL from the start on fresh installs (no rows exist when the create-migration runs).
4. (rev 4.2) **NOT NULL and no default.** A column default would turn a forgotten stamp into a row
   that belongs to a wrong or placeholder store; without one, an unstamped insert fails at the
   database — which is what covers the 81 bulk-write sites that skip model events (F33).
5. (rev 4.2) **Platform tier.** Two tables legitimately hold platform-level rows next to store rows:
   `audit_logs` (central operations, incl. the search admission override) and `settings` (P5). Their
   `tenant_id` is nullable; `NULL` means platform-level and is readable only in `MODE_CENTRAL`. Every
   other store-owned table is NOT NULL.
6. (rev 4.3) **Identifiers ≤ 63 bytes** (F30) — checked for **every** new or renamed index and
   constraint name, not only those that gain a column. Where a key column is nullable and NULLs
   must collide, use a **partial unique index** (`CREATE UNIQUE INDEX … WHERE …`): both PostgreSQL
   and SQLite support it. The rev 4.2 `NULLS NOT DISTINCT` idea is dropped — that syntax is
   PostgreSQL-only and unnecessary given partial indexes (B2).
7. (rev 4.3, F4) **Column type and engines.** `tenant_id` is declared `unsignedBigInteger`
   (house style), which on pgsql compiles to **BIGINT** — PostgreSQL has no unsigned integer type;
   the existing `PostgresUnsignedGrammar` adds `CHECK (tenant_id >= 0)`, and the referenced
   `tenants.id` is a bigserial, so the domains match. **Minimum supported PostgreSQL: 15** (the
   deploy stack runs `postgres:18-alpine`); the SQLite plane stays the default suite. Every
   engine-specific raw statement carries an explicit driver branch, and both planes must migrate —
   asserted by the suite itself (§10 row 70). PostgreSQL concurrency behaviour (locking, conflict
   handling, transactions) is verified only by the PostgreSQL suite using separate connections,
   never inferred from SQLite.

### 4.3 Cross-tenant reference integrity (layered)

Layer 0 — write-stamp coverage (rev 4.2, F33):

- Single-row saves: the `creating` hook of `TenantScopedTrait` (§2).
- Bulk writes (81 Eloquent `insert` / `upsert` / builder `update` sites) skip model events, so the
  caller supplies `tenant_id`; a forgotten value is rejected by the database (policy item 4), and
  (rev 4.5, F1) a **wrong** value is rejected by contract: bulk-write tenant ids come from
  `TenantContext`, never from request input or imported values — the provisioning path (§6.5
  Category 3) and the transfer import are the two narrow exceptions, each writing only ids it
  itself derived. **Enforcement mechanism (rev 4.6, F2):** the database's NOT NULL stops
  *missing* values and the immutability trigger stops *updates* — neither stops an *insert*
  carrying another tenant's id — so the actual guard is a Core stamping helper,
  `TenantWriteStamp::tenantIdForWrite(): int`, which reads `TenantContext` (throwing outside
  MODE_TENANT) and is the only sanctioned source of the column value at every registered bulk
  site; caller-supplied arrays and request data never carry `tenant_id` (the DTO/FormRequest
  layer rejects a submitted `tenant_id` outright), and the per-query architecture test verifies
  each registered site obtains the value through the helper. The allowlist is **per query site,
  not per file** (rev 4.5, F1):
  each registered entry names the table and the justification, and the test asserts the expected
  set — a new unsafe chain added to an already-allowlisted file still fails. Tests: rows 48, 102.
- Builder `update` / `delete` on an Eloquent model pass through the global scope.
- The 129 query-builder chains never see a scope. Every chain on a store-owned table gets either a
  tenant clause read from `TenantContext` or a `CentralOperations` wrap with a justifying comment;
  chains on installation-level tables (the 50 on search coordination, Auth, framework tables) are
  unaffected. An architecture test holds the allowlist — **per query site, not per file
  (rev 4.6, F1, matching the bulk-write rule below)**: each entry names the table, the operation,
  the tenant predicate or central-operation justification, and the expected source location, and
  the test detects a newly introduced chain even inside an already-registered file — the same
  discipline as the dispatch registry.
  (rev 4.3, F4 — the no-FK trade-off stated explicitly: `NOT NULL` prevents an omitted tenant id
  only; on tables without the FK, the database does not verify that the tenant exists. Ordinary
  invalid references are prevented at the application layer — the context tenant id always comes
  from a resolved, existing tenant row — and `tenancy:audit` reports orphaned tenant references;
  database-level tenant existence is deliberately not guaranteed for module tables.)

Layer 1 — scoped reads: all exists-checks (F14) flow through Eloquent models once those use
`TenantScopedTrait` → automatically tenant-filtered under MODE_TENANT. No signature changes at call
sites. `LanguageUniquePair` / `UniquePair` (F9) gain a tenant clause from `TenantContext`.
(rev 4.2) The 48 `unique:` / `exists:` validation sites (F33) belong to this layer: a `unique:` rule
on a store-owned table becomes a tenant-composite rule (house pattern: `ScopedRateUniqueRule`), an
`exists:` rule goes through the scoped gateway. An architecture test fails any bare `unique:` /
`Rule::unique` left against a store-owned table.

Layer 2 — validated writes: new Core helper `AssertsTenantOwnership` batch-checks supplied entity
ids through scoped gateway `exists` methods and rejects any id not owned by the active tenant.
Rules (rev 4, per review): validation runs **inside the same DB transaction as the write**; batch
validation completes **before any pivot synchronization or bulk insertion**. Applied to every write
path taking cross-entity ids without a FormRequest: Promotion products/categories, `post_product`,
`category_product` pivot syncs, Catalog importers, Transfer import/export, and directly callable
Actions. API FormRequests continue using scoped validation; background jobs inherit context per §6.1.
(rev 4.2) This is also what covers the 12 pivot `attach` / `sync` calls — pivots carry no
`tenant_id` of their own.

Layer 3 — intra-module composite FKs (rev 4 addition): **within a single module**, child tables add a
composite FK `(tenant_id, {parent}_id)` → `(tenant_id, id)` on the parent where practical
(e.g. Order items/returns/invoices → orders; Tax rates → tax classes; Catalog variants → products;
Webhook deliveries → endpoints/events). This gives DB-level cross-tenant protection for the most
common reference classes **without breaking module boundaries** (both tables live in the owning
module; the parent needs a `(tenant_id, id)` unique index to support the composite FK). Cross-module
references (promotions → products, posts → products) keep gateway validation only (F20 convention).

Layer 4 — audit: `tenancy:audit` uses a config-declared relation map (owning table → referenced
tables/columns, derived from module Schema constants, **including indirect relations through
pivots** — e.g. promotions→products via `promotion_products`). It reports both `tenant_id IS NULL`
rows and join violations where `owner.tenant_id <> referenced.tenant_id`. The map must cover every
declared cross-entity relation and evolve alongside schema changes. (rev 4.2: `tenant_id IS NULL` is
a violation except on the two platform-tier tables.)

Pivots between two tenant-owned tables (the 9 listed in §4.4) do **not** get their own
`tenant_id`; tenancy derives from the parent row and Layer 2 validates both sides at write time.

**Translation tables are different (rev 4.2 — supersedes the rev 4.1 sentence "tables joined by a
global table get `tenant_id` on the owning entity only"):** all 17 gain `tenant_id`. They carry
their own natural keys (8 × `[language_id, slug]`, F31), they are queried directly — a storefront
slug lookup starts at the translation row (`PicksTranslation`) — and a composite FK
`(tenant_id, owner_id) → (tenant_id, id)` on the owning table (Layer 3) keeps the stamped value
equal to the owner's.

### 4.4 Table classification (rev 4.2; rev 4.5–4.9 add the media coordination tables) — 94 store-owned, 39 installation-level

**Inventory reconciliation (rev 4.7 B5; updated rev 4.9):** 130 tables exist today = **91
store-owned + 39 installation-level** (the rev 4.2 baseline). Tenancy adds **8** tables: 5
Tenant-module tables
(§4.1 — `tenants`, `tenant_domains`, `tenant_memberships`, `tenant_languages`,
`tenant_currencies`) and 3 media coordination tables (`media_object_locks`,
`media_deletion_intents`, `media_object_generations` — rev 4.9). The classification below —
**94 store-owned + 39 installation-level =
133** — covers the 130 existing tables **plus** the 3 new media coordination tables; the
5 Tenant-module tables are metadata, classified separately in §4.1 (they are neither store-owned
business data nor installation-level reference data). Total after the tenancy migrations:
**138**. Phase 2's hard-gate dump must reconcile against these numbers exactly — nothing missing,
nothing double-counted.

**Installation-level — 39 tables, no store-ownership scope (rev 4.8, F2: some of these carry a
tenant **key** column — machine-token ownership on `personal_access_tokens`, spatie team identity
— which is a lookup key, never a scoping instruction; architecture tests must not read every
`tenant_id` column as store scoping):**

- Framework/app (6): `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
  `personal_access_tokens` (rev 4.4, B2: gains a **nullable `tenant_id`** — set at issue time on
  machine tokens, NULL on ordinary user tokens; a key column like `auth_roles.tenant_id`, not
  store ownership; indexed on the non-NULL part).
- Auth (13): `auth_users`, `auth_otp_codes`, `auth_social_accounts`, `auth_user_devices`,
  `auth_two_factor_challenges`, `auth_sign_in_lockouts`, `password_reset_tokens`, `sessions`,
  `auth_permissions`, `auth_roles`, `auth_role_has_permissions`, `auth_model_has_roles`,
  `auth_model_has_permissions`. (The last five gain `tenant_id` as the spatie team key, §5 — a
  key column, not store ownership.)
- Catalog search coordination (8): `catalog_search_gate`, `_admissions`, `_writer_leases`,
  `_operations`, `_replay_applied`, `_product_cursors`, `_retired_products`, `_worker_heartbeats`.
- Core (7): `core_countries`, `core_regions`, `core_languages`, `core_currencies`,
  `core_extensions`, `core_transfer_locks`, `transfer_installation`.
- Notification (3): `notification_preferences`, `notification_push_subscriptions`,
  `notification_telegram_subscriptions` — per user, across stores.
- User (2): `users` (profiles), user addresses — a customer's account and address book travel with
  the customer (rev 4.3, B4: the address book is user-scoped only, §2.1; a store's view of a
  customer's address is the `order_addresses` snapshot, which is store-owned).

**Store-owned — 94 tables: 85 gain `tenant_id` (82 existing tables + the three new media
coordination tables, created with it), 9 pivots inherit it from their parent.**

| Module | Tables |
|---|---|
| Audit (1) | `audit_logs` — platform tier |
| Cart (2) | carts, cart items |
| Catalog (30; 4 pivots) | products, variants, **variant_option_values** (pivot), images, product_shipping, product_translations, product_seo_translations, brands, brand_translations, categories, category_translations, **category_product** (pivot), **category_attribute** (pivot), options, option_translations, option_values, option_value_translations, attributes, attribute_translations, attribute_values, attribute_value_translations, attribute_groups, attribute_group_translations, attribute_templates, **attribute_template_groups** (pivot), product_attribute_values, product_comments, product_comment_reactions, featured_items, imports |
| Contact (2) | contact_messages, newsletter subscribers |
| Content (11; 2 pivots) | posts, post_translations, post_seo_translations, pages, page_translations, post_categories, post_category_translations, **post_category_post** (pivot), **post_product** (pivot), post_comments, featured_items |
| Core (3) | idempotency keys, transfer imports, transfer remaps |
| Discount (5; 2 pivots) | coupons, promotions, redemptions, **promotion_products** (pivot), **promotion_categories** (pivot) |
| Inventory (1) | inventory stocks |
| Media (6) | media, folders, media outbox, **object locks** (`media_object_locks`, rev 4.5, B5 — the per-key lease table of §6.4: `UNIQUE(tenant_id, object_key)`, `owner_token`, `generation`, `expires_at`; migration in Phase 4; expired rows pruned by the outbox sweep), **deletion intents** (`media_deletion_intents`, rev 4.6, B1/F4 — the durable intent table of §6.4: `owner_token`, `generation`, `status` enum(pending|executing|done|cancelled), partial unique `ON (tenant_id, object_key) WHERE status IN ('pending','executing')`; migration in Phase 4; terminal rows pruned by the sweep), **object generations** (`media_object_generations`, rev 4.9, B1 — the durable retirement record of §6.4: one row per physical key a store has ever published, `UNIQUE(tenant_id, object_key)`, `status` enum(eligible|retired); migration in Phase 4; **never pruned** — retirement must outlive intent pruning; status transitions `eligible → retired` only, enforced by a conditional update + engine-branched trigger, rev 4.10 — the `retired → eligible` rejection is tested through direct SQL and the application layer on both engines) |
| Menu (4) | menus, menu_translations, menu_link_translations, menu links |
| Notification (1) | notifications (judgment call: a notification row is about one store's order/ticket, and its admin recipients are that store's staff) |
| Order (8) | orders, order items, order addresses, invoices, invoice items, credit notes, returns, document sequences |
| Payment (4) | payments, refunds, payment methods, driver credentials |
| Setting (2) | `setting_settings` — platform tier; `setting_translations` |
| Shipping (6; 1 pivot) | zones, **zone_countries** (pivot), methods, method_translations, rates, shipments |
| Support (2) | tickets, ticket messages |
| Tax (2) | tax_classes, tax_rates |
| User (1) | wishlist items |
| Webhooks (3) | webhook endpoints, events, deliveries |

Names are as declared by each module's Schema (prefix `catalog_`, `content_`, … omitted for
readability). `catalog_search_*` rows stay installation-level because they coordinate writers of
one shared index (§6.8) and are keyed by product id, which is unique installation-wide.

### 4.5 Unique indexes — disposition of all 90 (rev 4.2)

PostgreSQL and SQLite both treat NULLs as distinct in an ordinary unique index. Where NULLs must
collide, the plan uses **partial unique indexes** (`CREATE UNIQUE INDEX … WHERE …`) — supported by
both engines — rather than PostgreSQL's `NULLS NOT DISTINCT`, which SQLite cannot execute (B2).
Raw statements follow the five existing `CREATE UNIQUE INDEX` precedents, each behind an explicit
driver branch where the engines differ.

| Class | Count | Rule |
|---|---|---|
| **T** | 22 | Natural key per store → gains a leading `tenant_id`, explicit name ≤ 63 bytes |
| **F** | 4 | Case-insensitive natural key → rebuilt as `(tenant_id, LOWER(col))` |
| **P** | 1 | Platform-tier key → `(tenant_id, name)` composite + partial unique on `(name) WHERE tenant_id IS NULL` |
| **R** | 3 | Replaced by a redesign |
| **S** | 33 | Stays, on a store-owned table: id-based keys (ids come from one database sequence, so they are unique installation-wide) and the order code |
| **G** | 27 | Stays, on an installation-level table |

- **T (22):** `catalog_attributes.code`; the eight `[language_id, slug]` keys (brand, category,
  option, option_value, product, page, post, post_category); `catalog_featured_items
  [featuredable_type, featuredable_id]`; `content_featured_items [same]` (both currently named at
  exactly 63 characters — explicit short names required); `cart_carts.user_id`; `cart_carts.token`;
  `menu_menus.location`; `order_invoices.number`; `order_credit_notes.number`;
  `payment_methods.method`; `shipping_methods.code`;
  `tax_rates [country_code, region_code, tax_class_id]`;
  `media_folders [name, parent_id]` — **plus a partial unique index on `(tenant_id, name) WHERE
  parent_id IS NULL` (rev 4.4, B3 — the index is per store; an installation-wide `(name)` index
  would stop two stores from both having a root folder named `Images`)**: today two root folders
  can share a name because NULL parent ids never collide (K10);
  `transfer_remaps [source_installation, module,
  entity, source_id]` (the leading column is the *destination* store); the `webhook_events` string
  key.
- **F (4):** `variants_sku_lower_unique`, `variants_barcode_lower_unique`,
  `newsletter_email_lower_unique`, `coupons_code_lower_unique`.
- **P (1):** `setting_settings.name` — a `(tenant_id, name)` composite for store rows **plus a
  partial unique index on `(name) WHERE tenant_id IS NULL`** for the platform tier: exactly one
  platform row per name, one row per store per name (P5).
- **R (3):** `idempotency_keys.key` → `UNIQUE(tenant_id, principal_key, key)` (§6.6);
  `payment_driver_credentials [driver, key]` → `UNIQUE(tenant_id, driver, key)` (§6.3);
  `order_document_sequences [type, year]` → `UNIQUE(tenant_id, type, year)` (§6.9).
- **S (33):** the seventeen `[owner_id, language_id]` translation keys;
  `catalog_product_shipping (product_id)`;
  `catalog_product_attribute_values [product_id, attribute_value_id]`;
  `catalog_variant_option_values [option_value_id, variant_id]`;
  `catalog_product_comment_reactions [comment_id, user_id]`; `discount_redemptions` (id column);
  `discount_promotion_products`, `discount_promotion_categories` (pivots);
  `inventory_stocks (variant_id)`; **`order_orders.order_code` — deliberately installation-wide**
  (a customer quoting a code, a payment reference, or a support ticket must identify one order
  without knowing the store); `order_invoices [order_id, revision]`; `order_credit_notes` (refund
  reference, nullable); `payment_refunds` (uuid); `shipping_rates [method_id, zone_id]`;
  `shipping_shipments (order_id)`; `user_wishlist_items [user_id, product_id]`;
  `webhook_deliveries [endpoint_id, event_id]`.
- **G (27):** `users_email_lower_unique`; `auth_social_accounts` ×2;
  `auth_permissions [name, guard_name]`; `auth_roles [tenant_id, name, guard_name]` (already the
  team key, §5) — **plus one added index (rev 4.3, B2): `auth_roles_platform_unique` on
  `(name, guard_name) WHERE tenant_id IS NULL`**, a partial unique index that enforces exactly one
  platform-role row per name at the database level on both engines — **plus two more added partial
  uniques (rev 4.4, F1): `core_currencies_one_default_unique ON (is_default) WHERE is_default`
  (at most one platform base currency) and `tax_classes_one_default_unique ON (tenant_id) WHERE
  is_default` (at most one default tax class per store); "at least one" is the seeders' and
  provisioning's job, and default changes are transactional** (the disposition count becomes
  91 + these); `auth_user_devices [user_id, device_name]`; the 6 search-coordination keys;
  `transfer_installation`, `core_languages.locale`, `core_extensions.slug`,
  `core_regions.code`, `core_countries` ×2, `core_currencies.code`, `core_transfer_locks.name`;
  `notification_push_subscriptions`, `notification_telegram_subscriptions` ×2,
  `notification_preferences [user_id, audience, code, channel]`; profiles `user_id`;
  `failed_jobs.uuid`; `personal_access_tokens.token`.

Of the 22 unique keys that contain a nullable column (F30), only `media_folders` and the two
platform-tier keys (`setting_settings` via class P, `auth_roles` via the added partial index) need
NULLs to collide; the others (`cart_carts.user_id` / `token`, credit-note refund reference, the
scalar rows of `catalog_product_attribute_values`, …) rely on NULLs being distinct and keep that
behaviour.

---

## 5. Auth, memberships, permissions — vendor teams schema verbatim

- Flip `config/permission.php` to `'teams' => true` and `'team_foreign_key' => 'tenant_id'`.
  Adopt the Spatie 8.3.0 vendor teams schema **verbatim** in
  `Modules/Auth/database/migrations/2026_09_08_153239_create_permission_tables.php`, already a
  verbatim copy of the vendor stub:
  - `auth_roles.tenant_id` is **nullable**. NULL means a global/platform role. Its unique key is
    `[tenant_id, name, guard_name]`.
  - `auth_model_has_roles.tenant_id` and `auth_model_has_permissions.tenant_id` are **NOT NULL**
    and the first column of their composite primary keys. No pivot team value is ever NULL.
  - This retracts rev-2 wording that made pivot `tenant_id` nullable. The global-role mechanism is
    implemented by nullable `auth_roles.tenant_id`, not nullable pivots.
- Platform super-admin is exactly one `SUPER_ADMIN` role row with `auth_roles.tenant_id = NULL`,
  carrying **no permissions**; the role name is used only for the platform bypass. Platform roles
  are seeder-written only (`updateOrCreate`). The existing `assertNotSuperAdmin`
  guard in `AbstractRoleAction` blocks admin role CRUD from creating NULL-team rows. (rev 4.3, B2)
  Exactly one platform-role row per name is enforced **by the database**: the partial unique index
  `auth_roles_platform_unique ON (name, guard_name) WHERE tenant_id IS NULL` (§4.5). The vendor
  `[tenant_id, name, guard_name]` unique cannot do it — PostgreSQL, like SQLite, treats NULLs as
  distinct, so duplicate NULL-team rows would otherwise be insertable. Spatie's role creation and
  lookup paths are unaffected: the partial index adds a constraint and changes no query semantics,
  and the vendor teams schema is otherwise adopted verbatim. A test asserts the invariant by
  attempting a duplicate platform-role insert (§10 row 69).
- `AuthServiceProvider::Gate::before` becomes team-context-immune: directly query the user's role
  assignment by user id, join `auth_roles`, constrain role name to `SUPER_ADMIN`, and require
  `auth_roles.tenant_id IS NULL`. Do not rely on the active spatie team or the `roles()` relation's
  team filtering. (rev 4.5, B8) the pivot is **polymorphic** — the query must constrain
  `model_id` to the user's key **and `model_type` to the user model's morph type, plus the
  expected guard name**; a numeric-id collision with a different model type can never trigger the
  bypass. `AccessGateway::adminUserIds()` applies the same model-type discipline. Test: row 103.
- Any attach of the platform role still writes a pivot team value. The platform seeder and
  `User::factory()->superAdmin()` must set `setPermissionsTeamId($defaultTenantId)` around the
  attach so the pivot's NOT NULL `tenant_id` is satisfied; restore the prior team context afterward.
- `AccessGateway::adminUserIds()` switches to a team-agnostic pivot-existence query; the `roles()`
  relation is no longer a safe all-team query after teams are enabled.
- Team context lifecycle: `AuthorizeTenantAccess` sets `setPermissionsTeamId($tenantId)` before
  permission checks and restores/clears it in a `finally`. Queue jobs that check permissions set
  it inside their tenant run (the §6.1 snapshot restores whatever preceded the job). Under
  php-fpm the container boots per request, so the team id cannot leak between HTTP requests;
  spatie's octane reset listener stays off (`register_octane_reset_listener => false`). (The
  rev 4.2 wording that managed this with `ResetTenantContext` is withdrawn by rev 6.)
  (rev 4.3, B1: membership and role writes within the active tenant execute in MODE_TENANT as
  tenant operations — §6.5 Category 2 — never through central mode.)
- Permission cache is confirmed team-agnostic: one flat cache key stores role/permission definitions;
  tenant filtering occurs in pivot SQL. No per-team cache key is required (but see §6.3 for the
  payment token cache, which IS tenant-scoped).
- Sign-in (`Modules/Auth/app/Actions/Public/Auth/SignInAction.php`) response gains `memberships`
  (from `tenant_memberships`, not roles) and `active_tenant` when unambiguous; Sanctum tokens remain
  global user credentials and tenant selection is per request. (rev 4.2: with several memberships
  the admin panel shows the store switcher and sends `X-Tenant-Id` on every request — A5 — and the
  switch clears the query cache, §8 Phase 7.)

---

## 6. Background execution, cache, storage, webhooks, payments, idempotency — and the subsystems
## that assume one store

### 6.1 Queue lifecycle — snapshot/init/restore, both execution models

`Queue::createPayloadUsing` stamps every payload at dispatch time:
`{tenant_id: <id>|null, central_operation: <operation id>|null}` (rev 4.7, B2: the payload
carries the **registered operation id** a centrally-dispatched job must invoke — never a mode
grant; no payload field can set MODE_CENTRAL).
This covers every dispatch origin (review requirement):

| Dispatch origin | Stamp | Why |
|---|---|---|
| HTTP request (tenant context) | tenant id | middleware resolved it |
| HTTP request (admin/central action) | the registered operation id | `CentralOperations` context — the job must re-invoke it via `run()` |
| Console command (scheduled/ops) | operation id or tenant | commands declare their run mode (§6.7) |
| Job dispatched from another job | parent's effective mode | stamp reads the live context at dispatch |
| Delayed / retried job | preserved | the stamp is part of the stored payload JSON |
| Seeder / migration | central | explicit |

Execution-side listeners (registered in `CoreServiceProvider`):

- **`JobProcessing`**: push the current context onto a **stack of snapshots** (rev 4.1: a single
  mutable snapshot would be overwritten by nested execution), then **deterministically initialize**
  from the payload markers: tenant id → MODE_TENANT; neither marker →
  MODE_INACTIVE. **A `central_operation` marker does NOT set any mode** (rev 4.7, B2): the
  listener only validates that the id exists in the `CentralOperations` registry and that the job
  class is allowlisted for it — the job body must perform its cross-tenant reads by invoking
  `CentralOperations::run($operation, $reason)` itself, where the registry, call-site policy,
  actor policy, reason auditing, and exception-safe boundary are enforced. `CentralOperations`
  stays the **single** entry to MODE_CENTRAL, queue path included; an architecture test fails any
  listener or job code that sets central mode from a payload (row 118). No job ever inherits the
  previous job's residue, and a stale/leaked context is
  overwritten here regardless of cleanup failures. (rev 4.2: the snapshot also captures the
  store-runtime values of §6.7, and a tenant-initialized attempt applies the store runtime for
  that tenant before the job body runs.) (rev 4.7: the `JobAttempted`/`SyncQueue` lifecycle this
  section relies on was independently verified against the Laravel 13 framework source by the
  fifth review — B3 withdrawn; F27 stands.)
- **`JobAttempted`** (fires in the worker/sync `finally` after **every** attempt — F19, F27):
  **pop the stack and restore exactly that snapshot**, not blind deactivation. This is the critical
  rev-4 fix: under the sync driver (F27) a job runs inside the HTTP request, so restoring returns
  the request to its own tenant context; in a real worker the pushed snapshot is the between-jobs
  state (inactive), so cleanup still lands. Nested execution (request → sync job → sync job, each
  possibly pinned to a different tenant) unwinds correctly because each attempt pops the snapshot
  its `JobProcessing` pushed — a shared variable would clobber the outer state (rev 4.1, review
  finding 2; tested by §10 rows 26–27 and the nested cross-tenant case in row 40).
  `JobExceptionOccurred`/`JobFailed` paths are covered by the same `finally` event.

`CentralOperations` job marker (§6.5) is not a generic bypass: jobs receiving `central=true` are
still limited to the allowlisted classes; the architecture test (§6.5) fails any other class that
reads or relies on the marker.

Defense-in-depth: an opt-in `TenantAware` job middleware (per-attempt `try/finally`, payload-stamp
pinned) is provided and used explicitly by `DeliverWebhookJob` (which needs row-authoritative
rehydration, §6.2). Tests cover **both execution models independently** (review requirement): sync
inline dispatch under feature tests, and a real worker pass (`queue:work --once` on the database
driver) in the Tenant module suite — §10 rows 26–29.

(rev 4.2) `NotificationCreated` (`ShouldBroadcast`, F36) joins the dispatch registry as a
broadcast entry so the registry stays complete (K7).

### 6.2 Webhooks outbox

`webhook_events`/`webhook_deliveries` gain `tenant_id` (stamped at write from request context).
`DeliverWebhookJob` bootstrap is explicit:

- Payload-stamped tenant branch: enter MODE_TENANT from the payload, then load
  `WebhookDelivery::find($deliveryId)` under the scope. A stale/mismatched stamp yields null and
  the job fails loudly; no fallback to central mode.
- Central-dispatch branch (scheduled `webhooks:dispatch-deliveries` or admin retry): a narrow
  MODE_CENTRAL lookup **by primary key only** (executed via `CentralOperations`), then
  `runForTenant($delivery->tenant_id, ...)` for all delivery work.

The raw `DB::table` attempt lookup (F9) becomes an Eloquent query or receives an explicit tenant
clause. `PruneWebhooksCommand` runs per store (tenant fan-out, §6.7).

### 6.3 Payments — per-tenant configuration resolver (A2)

New contract in `Modules/Core/app/Contracts/Gateways/Payment/`:

```php
interface PaymentConfigurationResolverInterface
{
    /** @throws PaymentConfigurationMissing — never falls back to another tenant or to platform defaults in production */
    public function get(string $driver): PaymentConfiguration;
}
```

`PaymentConfiguration` DTO carries decrypted-on-demand credentials, per-tenant `webhook_secret`,
mode/env, return-URL base, and a `credential_version` (incremented on config change).

**Storage (rev 4.2 — supersedes the `payment_gateway_configs` table of rev 4.1):** the existing
`payment_driver_credentials` table (F42) becomes the per-tenant store. It gains `tenant_id`
(NOT NULL); its unique becomes `UNIQUE(tenant_id, driver, key)` (class R, §4.5); any column the
contract needs that the table lacks today (`enabled`, `credential_version`, the per-tenant
`webhook_secret` if it is not already a credential key) is added in place. `ResolvesPaymentCredentials`
is reworked into the tenant-scoped resolver: it reads through the scope, caches under
`{tenant_cache_prefix}payment.credentials:{driver}:{credential_version}` (the unscoped 15-minute
`payment.credentials` key is removed), and uses the `.env` baseline (`$baseline`) only in `local`
and `testing`. **Cache identity (rev 4.7, F1):** `credential_version` is **per (tenant, driver)
row** — each driver's credential write bumps that row's own version atomically, and the resolver
cache key includes the driver, so versions never collide across drivers and a rotated Stripe key
never invalidates the PayPal entry (the PayPal token cache key is already per driver). Its
`catch (Throwable)` sites and those of `ApplyDatabaseCredentials` (F38)
rethrow tenancy exceptions instead of falling back to the baseline.

Refactor scope (complete, from F25) — every site switches from `config('payment.*')` to the
resolver: clients `StripeCheckout`, `PaypalClient`, `CheckoutcomClient`, `NowpaymentsClient`,
`AdyenCheckout`; drivers `StripePaymentDriver`, `AdyenPaymentDriver` (incl. `return_url`),
`RazorpayPaymentDriver`, `MercadopagoPaymentDriver`, `NowpaymentsPaymentDriver`,
`CheckoutcomPaymentDriver`, `ManualPaymentDriver`; availability gating `PaymentDriverManager`
(per-tenant enabled check).

Cache correctness (F11 reclassification): the PayPal access-token cache becomes
`{tenant_cache_prefix}:payment.paypal.access_token:{credential_version}` — scoped by tenant AND
credential version so a rotated credential can never reuse another tenant's/version's token. No
other driver caches tokens today; the resolver centralizes any future caching with the same key
discipline.

Operational rules (rev 4.1 adds review finding 4's three hardening requirements): credentials are
decrypted only at use, never logged, never serialized into API responses or exception context
(Audit redaction already covers `secret/token/password/key` keys — F21); signature verification
always uses the resolved tenant's secret. In addition:

- **Atomic version bumps**: updating a tenant's gateway config increments `credential_version` in
  the same DB transaction as the credential write, so no observer can ever see new credentials
  paired with an old version (and vice versa).
- **Disabled means unusable**: `enabled=false` makes the resolver throw
  `PaymentConfigurationMissing` for that driver — direct construction or invocation of a client or
  driver (bypassing any availability UI or method listing) cannot use stored credentials.
- **No fallback, anywhere**: a missing tenant configuration throws before any credential use —
  including inside exception handlers and webhook verification; a failed lookup never retries
  against platform defaults (production) or another tenant's credentials, and a signature check
  against the wrong secret fails closed rather than falling back to a global secret.

Webhook routing: `POST /webhook/{tenant-slug}/{driver}` — Stage 2 resolves the slug as the
authoritative tenant source (§3.1, B2 — before credential application and any scoped binding,
headers ignored) → reject suspended tenant →
verify signature with that tenant's secret → `runForTenant` for transaction lookup and outcome
writes. Return URLs derive from that tenant's verified domains. No tenant header is trusted on
webhook routes. (rev 4.2) The `payments.transaction_id` lookup runs under the tenant scope (today
it is global, F12/F42); return URLs use the tenant's primary domain and the request locale instead
of env + hard-coded `/en/`.

`ApplyDatabaseCredentials` is deleted once the last F25 read-site goes through the resolver; until
then it stays registered after `ResolveTenant` (§3.1).

### 6.4 Media

- Rows: media, folders and the publication outbox are store-scoped (§4.4). `media_media_outbox`
  gains `tenant_id`, so the processor derives the final key from the row itself.
- **Keys (rev 4.2 P4; generation-unique rev 4.8; lifecycle record rev 4.9, B1):** two distinct
  operations, kept distinct from here on — **physical object creation** writes a new immutable
  object under `media/{tenant_id}/{ab}/{sha256}-{ulid}.{ext}` (content-addressed with a store
  prefix **and a unique generation suffix**; the key is never reused), while a **logical media
  publication/reference** records a media row that points at one exact physical generation — its
  own new one, or an **existing eligible** generation (§6.4 reuse rule). Identical files
  uploaded by two stores always mean separate objects, so `DeleteMediaAction` can never remove
  another store's file (today the key is shared installation-wide and deletion is
  unconditional — F45). Each media row stores the **exact physical key** it references — a
  reference never points at "the content", it points at one object generation.
  **Durable generation record (rev 4.9, B1):** every physical key a store has ever published has
  a row in `media_object_generations` (`tenant_id`, `object_key` unique per store,
  `status` enum(eligible|retired), timestamps), written in the same transaction as the object's
  first reference. Reuse eligibility consults **this record** — never the presence or absence of
  a deletion intent — so retirement survives intent pruning by construction.
- Staging stays `.staging/media/{uuid}.{ext}` (unique by uuid); the outbox row carries the tenant
  and the final key. Variants derive from the original's directory — per store automatically.
- **Same-key serialization (rev 4.3; lease/fencing rev 4.4, F2).** Publication finalize and
  deletion serialize per `(tenant_id, object key)` through a **database lease**,
  `media_object_locks` (`tenant_id`, `object_key`, `owner_token`, `generation`, `expires_at`),
  acquired with the same lease + fencing protocol as `core_transfer_locks` (DB-clock expiry,
  fencing generation, reclaim advances it). Entries touching the same key are processed serially:
  a new media row referencing an existing key acquires the lease before its transaction commits,
  so a concurrent deletion either sees the new reference (and keeps the file) or the publication
  lands after the unlink (and its own outbox entry re-publishes the file). **The unlink is
  conditional**: one transaction rechecks references, verifies the lease is still owned
  (`owner_token` + generation match, lease unexpired), and unlinks — a worker whose lease expired
  fails closed before touching the filesystem; long operations (variant regeneration) renew the
  lease. A crashed worker's lease expires and the next claimant advances the generation — the old
  owner is fenced out exactly as in the transfer design (postgres-migration §3.1).
  **Deletion is a durable state machine whose critical section is a held row lock, not an inline
  unlink (rev 4.5 B5; fencing closed rev 4.6, B1).** Intents persist in their own classified
  table, `media_deletion_intents` (§4.4): id, `tenant_id`, `object_key`, `owner_token`,
  `generation`, `status` enum(pending|executing|done|cancelled), timestamps, and a partial unique
  index `ON (tenant_id, object_key) WHERE status IN ('pending','executing')` — at most one active
  intent per key. Permitted transitions: `pending → executing` only under the lease lock below;
  `executing → done` idempotently after the unlink (a missing object already counts as deleted);
  `pending → cancelled` when the reference recheck finds a live reference. Protocol:
  (1) acquire the per-key lease; (2) transaction T1 — recheck references, insert the intent
  (`pending`, carrying `owner_token` + `generation`), commit; (3) transaction T2 — `SELECT … FOR
  UPDATE` the `media_object_locks` row, **re-verify under that lock** that `owner_token` and
  `generation` are still ours and the lease is unexpired, transition the intent to `executing`,
  then **perform the unlink while T2 is open** — every lease ownership transfer (reclaim or
  renew) is a write to that same row, so it blocks on the lock: a newer owner cannot appear
  while the unlink is in flight, and a worker whose lease expired before T2 sees it under the
  lock and aborts without touching the filesystem. The open row-lock transaction spanning the
  filesystem call **is** the critical section — the filesystem cannot check a fencing token, so
  the database lock carries the exclusion for it; (4) commit T2 and finalize (`done`, or a
  follow-up idempotent finalize). **Recovery:** a crash at any point drops the connection and
  releases the lock; the intent stays `executing`; the sweep re-processes executing intents —
  object missing → `done`; object present → take a fresh lease and re-run the unlink phase
  (references rechecked again). An ambiguous storage failure leaves the intent `executing` for
  the same idempotent retry. Duplicate sweep attempts are safe because every transition is
  conditional on status + lease identity. Publication detects and serializes against an active
  intent: it takes the same per-key lease and treats an active intent as "resolve first" — wait
  for `done`/`cancelled` or process it — before committing a new reference.
  **Generation-safe object lifecycle (rev 4.8, B1; durable retirement rev 4.9 — supersedes
  rev 4.7's rename-then-unlink
  reasoning).** A filesystem rename is atomic but not conditional on the lease generation, and a
  worker suspended after losing its database session can resume arbitrarily later and execute a
  previously authorized rename against a path recovery has since handed to a new publication — a
  row lock cannot prove the old process stopped. The fix makes **physical object identity
  immutable and generation-specific**, so a stale syscall can never reach a newer generation:
  - a logical reference points at one exact physical key (see the Keys bullet); **once a
    deletion intent targets a key, the key's `media_object_generations` row is marked `retired`
    in the same transaction — permanently**. Cancelling the intent never un-retires; pruning the
    intent never touches the generation row; the next publication of the same content therefore
    writes a fresh generation, because reuse eligibility reads the generation record, not intent
    history;
  - the deletion intent therefore names the **exact key** it removes. The T2 rename-into-trash
    (still inside the row-lock critical section) can only ever move a retired object: a stale
    worker resuming after recovery performs its rename against the retired key, and the new
    publication's generation is a **different object that remains untouched**. The trash unlink
    stays idempotent and outside the critical section;
  - recovery is unchanged and simpler: the sweep finalizes (object gone → `done`) or cancels the
    intent when a live reference exists — and since a post-recovery reference always points at a
    new generation, a cancelled intent just leaves the retired key to trash collection. No
    live-referenced object can be lost, and the stale-worker race is eliminated rather than
    bounded.
  **Explicit invariants (rev 4.9, per review):** the exact physical key is immutable once
  assigned to a media reference; an intent's target key cannot change across retries or
  recovery; a retired generation never transitions back to eligible (cancellation included);
  intent pruning can never remove the durable retirement record; cleanup never derives a
  deletion target from a content hash alone — only from the exact key stored on the validated
  tenant-owned record; and a new generation must be **fully published before its reference
  becomes visible** (the object is written and finalized first, the reference row commits
  pointing at it afterward — a partially written object is never a valid referenced
  generation).
  **Common serialization point (rev 4.10, B1 — the last concurrency guarantee).** The generation
  record is the authority for reuse, so every path that can attach a reference and every path
  that can retire a generation serializes on **the same `media_object_generations` row**:
  - **reference attachment / reuse:** `SELECT … FOR UPDATE` the generation row, verify it is
    still `eligible`, and **hold the lock through the transaction that inserts the media
    reference** — a concurrent retirement blocks and, once it proceeds, sees the new reference
    and follows the correct behaviour for a now-referenced object (no retirement of a live
    generation);
  - **deletion-intent creation:** lock the same row, verify the current reference state, mark
    the generation `retired`, and create the intent **in one transaction** — a concurrent
    attachment that lost the race finds the row `retired` on its own locked read and publishes
    a fresh generation instead;
  - **retirement is one-way:** the transition is a conditional
    `UPDATE … WHERE status = 'eligible'` (application guard), backed by a database safeguard —
    a `BEFORE UPDATE` trigger on `media_object_generations` rejecting any `retired → eligible`
    change (the §6.8 engine-branched trigger pattern);
  - **lock ordering:** the generation row is always taken **before** any media reference rows,
    on every path — publication and deletion cannot deadlock against each other.
  - **every reference-set mutation follows the protocol (approval clarification):** attachment,
    **detachment/removal**, and any other change to a generation's reference set acquires the
    generation-row lock first, before locking or modifying media reference rows — and the
    reference-count/existence checks that decide whether a generation can be retired run
    **while holding that lock**. Deletion that removes a reference and then evaluates
    retirement is not an exception; the rule applies to the whole reference-set lifecycle, not
    only to attachment and intent creation.
  A physical object may be created before its generation row exists (first publication), but
  from the moment the record is available the protocol is mandatory on both sides. Test:
  row 123 — the deterministic two-connection PostgreSQL race, both orderings.
  **Trash exposure boundary (rev 4.8 F3; private disk rev 4.10):** the physical trash location
  is `trash://{tenant_id}/…` on a **dedicated private disk** — a storage root with **no public
  web-server mapping at all**, not a sibling directory under a served root (exclusion from
  Laravel's URL generator alone does not prove the web server cannot serve a directory): not
  addressable through any media URL, not reachable over HTTP, readable only by the cleanup
  process. (The earlier `media/{tenant_id}/.trash/` spelling was logical naming only.) Tested at
  the storage/HTTP boundary — both direct HTTP access and the configured disk's URL-generation
  behaviour (row 121). Tests:
  rows 79, 91, 99–100, 112, 116–117, 120–123 — including the deterministic pause/resume race
  (row 120): worker A suspended after session loss, worker B recovers, cancels, and publishes a
  new generation, A resumes its rename — the new publication stays accessible and its exact
  object generation remains intact.
- `catalog_images.url` and other stored URLs become relative keys; absolute URLs are rendered with
  the resolved store's host (Phase 6). Today the installation host is baked into the row (F45).
- `MediaGateway::upsertLocal` takes the acting user explicitly instead of hard-coding user 1 (K6).
- Export artifacts: exports are streamed today (F43), so nothing is stored. If an artifact is ever
  stored it goes under `tenants/{id}/` and is downloadable only through tenant-scoped authorization
  (§9.7).

### 6.5 Execution boundaries — platform operations, tenant operations, provisioning (rev 4.3, B1)

MODE_CENTRAL remains reachable through exactly one service,
`Modules/Core/app/Support/Tenancy/CentralOperations.php`:

```php
final class CentralOperations
{
    /**
     * Runs a REGISTERED operation handler. The registry maps the operation class to its
     * authorized implementation; the caller passes a typed operation object with validated
     * arguments — never a closure (rev 4.5, B4).
     */
    public function run(CentralOperation $operation, string $reason): mixed;
}

interface CentralOperation
{
    public function id(): string;
}
```

The registry binds operation identifiers to **implementations, not strings** (rev 4.5, B4): every
operation is a final class; the registry maps it to the single action/service allowed to execute
it; and an architecture test binds each operation to its **authorized call sites**, so reusing a
registered name from an unauthorized caller fails the build even though the name exists.
(rev 4.6, F3: the registry key is the **exact final operation class** — `id()` is display and
audit labelling only; a different class implementing `CentralOperation` and returning the same
string obtains nothing. The architecture test verifies that the registered operation class,
handler, and caller set stay aligned.) Actor
policy is defined at the boundary itself: HTTP platform operations run on super-admin-only
routes; scheduled console operations execute as the system actor with the command + reason
recorded. The central-mode guarantee never depends on a developer remembering the right caller.

But central mode is now reserved for **cross-tenant reads and platform-tier writes** — never for
writing store-scoped rows. Three categories cover every workflow the review found conflicting:

**Category 1 — platform operations (super-admin + `CentralOperations`).** Tenant CRUD,
suspension, deletion; domain administration (attach, release, force-revoke, verification
bookkeeping); platform settings; membership writes that are *not* confined to one active tenant
(a platform-initiated owner reset); transfer/search/media platform bootstrap; `tenancy:audit`;
seeders; and the `order.lookup` read of §6.9. All exactly as rev 4.2 defined them: super-admin-only
routes behind `PermissionMiddleware` + Gate::before, registry + mandatory reason, exception-safe
restore; no request parameter, header, or user-controlled input reaches `run()`'s arguments.

**Category 2 — tenant operations (MODE_TENANT, permission-guarded).** Membership invite /
activate / suspend / remove and role assignment **within the active tenant** run in ordinary
tenant mode: the rows carry the context tenant, and a model-level guard on `TenantMembership`
rejects any write whose `tenant_id` differs from the context (an architecture test also fails
membership/team writes made outside these actions). Authorization is the existing
`PermissionMiddleware` with tenant roles (`tenant.memberships.manage`, role-CRUD permissions);
`AbstractRoleAction::assertNotSuperAdmin` already blocks NULL-team role writes; ownership transfer
additionally requires `is_owner` or super-admin. Nothing here enters MODE_CENTRAL, and a store
admin's reach is structurally limited to their own store (A3). **Owner-set serialization
(rev 4.7, B6):** every mutation capable of changing a tenant's active-owner set — removal,
suspension, demotion, role change, ownership transfer — takes `SELECT … FOR UPDATE` on the
tenant's row **before** checking and changing, so competing transactions block and re-check
instead of both passing a read-committed check (the two-owners-both-deactivate race). Tested with
competing operations on two separate PostgreSQL connections (row 119); a sequential last-owner
test alone proves nothing.

**Category 3 — tenant provisioning (self-serve, no central mode).** `ProvisionTenantAction`
(Tenant module, **`onboarding` audience** — the tenant-neutral route of §3.1, canonical
`POST /api/v1/onboarding/tenants`; requires an authenticated global user, never a resolved
tenant or membership; rate-limited) validates the authenticated initiating user, applies
onboarding limits (max active tenants per user, configurable; slug uniqueness with collision
suffixes), and creates — in **one transaction** — the tenant row, its `<slug>` subdomain domain
row, the initial owner membership, the owner role assignment, the default settings/menu, and the
default language/currency links. Every table it touches is either the Tenant module's own
unscoped metadata tables or is written through this one narrow, audited path; nothing commits
partially. **Context lifecycle inside the transaction (rev 4.7, B7):** the request context stays
MODE_INACTIVE at the boundary — the tenant and domain rows are unscoped metadata writes — and
once the tenant row exists the action enters **`runForTenant($newTenantId)`** for the owner
membership, the owner role assignment, and the tenant-owned defaults (settings, menu,
language/currency links), restoring MODE_INACTIVE in `finally`. Scoped writes therefore happen
under a real tenant context derived **only** from the id this transaction just created — the
onboarding caller never supplies one — and the fail-closed rule is never weakened. The spatie
team id is set to the new tenant around the role attach and restored afterward. The action never
enters MODE_CENTRAL and is not in the registry. Audit: a `tenant.provisioned` event through the
existing F21 map.
(rev 4.5, B1: the endpoint carries the tenant-neutral **`onboarding` audience** — §3.1 — so Stage
2 performs no tenant resolution at all: it works from the central API host where nothing resolves,
and from an existing store's host the context still stays MODE_INACTIVE for the action. Test
row 95.) The quota check is serialized per initiating user (rev 4.5, F4): the user row is locked
(`SELECT … FOR UPDATE`) before counting memberships and creating — a transaction alone does not
stop two concurrent onboarding requests both passing the limit (test row 105, two PostgreSQL
connections). Owner invariant (rev 4.5, F4): a tenant always retains at least one active owner —
ownership transfer grants and revokes in one transaction, and removing or deactivating the last
owner is rejected (Category 2 enforces it).
**Provisioning write contract (rev 4.4, F3):** the newly created tenant id is the **only**
tenant id the path may write — every store-owned insert supplies it explicitly; the path cannot
read or update any other tenant's rows; the query-builder architecture test recognizes only this
one narrowly named path as the MODE_INACTIVE exception; the initial owner-role pivot is assigned
with `setPermissionsTeamId($newTenantId)` and the prior team context restored afterward; any
failure rolls back the entire transaction. Test: row 94.

- The operation registry stays an explicit, reviewed list in Core — Category 1 above enumerates it
  (incl. the rev 4.2 search/media bootstrap entries) — but each entry now binds operation class →
  handler → call sites (B4). Adding or rewiring an entry is a visible diff reviewed like a
  permission grant.
- `$reason` is required and recorded: central admin operations emit an Audit event (F21 map) with
  actor, operation, reason; console operations log the command + reason.
- Exception safety: `run()` restores the previous mode in `finally`.
- `TenantContext::central()` is marked `@internal`; the architecture Pest test fails any direct use
  of `->central(`/`MODE_CENTRAL` outside `CentralOperations` + `TenantContext` itself, fails any
  `CentralOperations::run()` call site outside the operation's allowlisted callers (rev 4.5, B4 —
  a registered name invoked from an unauthorized call site fails even though the name exists,
  row 98), and (rev 4.3) fails any **write to a store-scoped model while in MODE_CENTRAL**.
- Tests: rows 21–22 (refused/failed Category-1 attempts leave context unchanged) and rows 71–72
  (provisioning and tenant-operation writes work without central mode).

### 6.6 Idempotency — concurrency control, not just a response cache (rev 4, per review)

Redesign (Phase 4), fixing F16 + F26:

1. **Identity** (rev 4.1, review finding 1 — nullable `user_id` would let NULL duplicates through
   a unique index): edit `2026_03_25_092852_create_idempotency_keys_table.php` in place (A1):
   add `tenant_id` (NOT NULL), a **NOT NULL `principal_key`** string, and `status`
   enum(processing|completed); **replace the global `KEY` unique with
   `UNIQUE(tenant_id, principal_key, key)`**. `principal_key` identifies the caller for **every**
   request: authenticated users get `user:{id}`; guests get `guest:{sha256(X-Cart-Token)}` — the
   existing unguessable per-session UUID header, which is stable across legitimate retries (the
   client resends the same token) and is not forgeable into someone else's principal (a made-up
   token only loses the caller's own dedup protection; it grants access to nothing). `user_id`
   stays as a nullable traceability column; it is never part of the unique identity.
2. **Atomic reservation with fencing (rev 4.3, B3)**: `IdempotencyMiddleware::handle()` inserts a
   claim row — `claim_token` (fresh uuid), `expires_at`, status=processing — **before** executing
   the operation, as one statement: `INSERT … ON CONFLICT DO NOTHING` (+ `RETURNING` where
   available). The insert relies on the composite unique constraint (all-NOT-NULL columns, so the
   guarantee holds for guests too — §10 row 39):
   - Insert succeeds → this request owns the key; the claim token is this request's fencing
     identity. Proceed.
   - Conflict → an existing row: `completed` → replay the stored response
     (`X-Idempotency-Response: true`); `processing` and unexpired → **409
     `core::errors.idempotency_in_progress`**; `processing` and expired → reclamation (below).
3. **Fenced reclamation**: an expired `processing` row is reclaimed by a **conditional DELETE
   followed by a guarded INSERT** — `DELETE … WHERE id = ? AND claim_token = {old} AND
   expires_at < now() AND committed_at IS NULL`, then the same one-statement reservation insert
   as above. (rev 4.5, B3 — the `committed_at IS NULL` guard is the load-bearing one: a claim
   whose business work committed can **never** be reclaimed or deleted by the retry path; it
   stays put and surfaces `idempotency_unresolved` until operator recovery.) Concurrency of the
   two-step sequence (rev 4.6, B3, wording corrected — reclamation is a DELETE + INSERT, not an
   update): the DELETE and the completion UPDATE lock the same row, so a marker write and a
   reclaim serialize on the row and cannot both win; two concurrent reclaimers serialize the same
   way — the second DELETE matches 0 rows and stops — and after a successful DELETE the
   reclaimers' INSERTs race under the composite unique, where exactly one wins and the loser
   gets the conflict → 409. The original request may still be
   running (slow, not crashed) — its later
   completion matches **0 rows** (its token no longer owns the row) and can never overwrite the
   new owner's result; the stale claimant logs, surfaces 409, and never re-executes.
4. **Completion is fenced, runs in `handle()`, and is crash-aware (timing rev 4.5, B3)**: the
   response is persisted and claim ownership verified in `handle()` **after `$next($request)`
   returns but before the response reaches the framework** — a terminating middleware runs after
   the response has been sent and cannot change it; `terminate()` keeps only failure cleanup,
   and **every deletion path without exception — `terminate()` included — carries the same
   `AND claim_token = {ours} AND committed_at IS NULL` guard** (rev 4.6, B3): a claim whose
   business work committed is never deleted by cleanup, whatever fails downstream; a
   response-persist failure after a committed marker **updates** the claim to surface
   `idempotency_unresolved` (audited) rather than deleting it — a committed operation is never
   converted back into a clean retry opportunity (test row 111). Completion is
   `UPDATE … SET status = completed, response = … WHERE id = ? AND claim_token = {ours}` — always
   scoped by tenant + principal. **If the completion update affects zero rows**, ownership was
   lost: `committed_at` set → the request responds **409 `core::errors.idempotency_unresolved`**
   and the computed response is discarded; otherwise the marker abort already rolled the business
   work back and the error response propagates — a request never returns another claimant's data.
   **Crash window** (business committed, response never stored): actions participating in
   idempotency stamp `committed_at` on the claim **inside the business transaction, before it
   commits**. The helper updates by claim id + claim token, **requires exactly one affected row,
   and throws on zero** — a request that lost ownership (its claim was reclaimed mid-flight)
   cannot commit unmarked business work; the throw rolls the whole business transaction back, so
   marker and business write are one commit by force, not by convention. **Marker participation
   is mandatory (rev 4.5, B3): an architecture registry fails any idempotent route or action
   whose business transaction can commit without the helper.** Recovery
   policy for an expired claim:
   - no `committed_at` → nothing durable happened → safe to reclaim and re-execute;
   - `committed_at` set, no stored response → **never re-execute**: the key returns
     **409 `core::errors.idempotency_unresolved`** and is audited for operator review.
   External side effects (payment creation and the like) are initiated only through providers with
   their own idempotency keys or through the durable outboxes (the dispatch registry's REQUIRED
   class rule), so a replay cannot double-charge; DB-only work is covered by the same-transaction
   marker. **Provider idempotency keys derive from the stable logical request identity
   (`tenant_id + principal_key + key`) — never from the claim token** — so a reclaimed claim
   reuses the same provider key and cannot duplicate an external effect. Tests: rows 76–78, 88–89.
5. Replay matching in `handle()` includes `tenant_id` + `principal_key` — cross-tenant and
   cross-principal replay are structurally impossible (§10 rows 11, 30, 39).

### 6.7 Store runtime — per request, per job, per command (rev 4.2)

Today the store's settings are read once per process: `SettingServiceProvider::boot()` applies the
timezone and the store identity at boot (F39), languages and currencies carry single-store flags,
and one settings table holds both platform and store values (F46). Under the Octane runtime the
first store to boot configured every request; the php-fpm revert removed that HTTP staleness, and
what §6.7 still must close is the queue-worker path — and the boot-time apply itself, so a
settings change takes effect on the next request rather than at the next deploy.

1. **Per request.** After `ResolveTenant` identifies the store, Core raises a `TenantResolved`
   event (a Core contract); the Setting module listens and applies the store runtime: `app.timezone`
   (+ `date_default_timezone_set()`), store identity (`config('store.*')`), default language and
   supported locales, enabled currencies and default display currency (P2), pricing mode
   (`prices_include_tax`), default tax class, FX values, mail from-name. `SettingServiceProvider::boot()`
   no longer applies anything per process.
2. **Apply per request.** Under php-fpm the framework boots per request, so the platform
   defaults hold until `TenantResolved` fires; `SettingServiceProvider::boot()` no longer applies
   anything either (item 1). `UpdateSettingAction` keeps its invalidation (F11) and the next
   request re-applies — a settings update reaches every HTTP request by construction (the
   Octane-era defect K8 ceased to exist with the runtime).
3. **Per job.** Part of the §6.1 snapshot: `JobProcessing` applies the runtime for the payload's
   tenant, `JobAttempted` restores the captured values.
4. **Process-global values.** `date_default_timezone_set()` and the statics of F39 are
   process-wide. Under php-fpm one worker process serves one request at a time, and queue
   workers are separate processes, so set-at-request-start (item 1) and the §6.1 job snapshot
   cover every runtime. `SupportedLocales::$codes` becomes tenant-keyed.
   `ResolvesPaymentCredentials::$baseline` is platform data (env) and stays as it is.
   `SearchMutationGate::$depth` and `SearchCursorManager::$held` are transaction bookkeeping
   that holds no store data — released in `finally`, unchanged.
5. **Fail closed inside the resolvers.** The seven `catch (Throwable)` sites of F38 rethrow
   tenancy exceptions (`TenantContextMissingException` and the tenant-not-found family). Their
   fallback to platform defaults stays for infrastructure failures only (a database outage must not
   take a request down), and it is safe because a platform default is never another store's value.
6. **Caches.** Every store-dependent key of F37 is built through `TenantContext::cachePrefix()`
   (`t{id}:setting.timezone`, …); `UpdateSettingAction` invalidates with the same prefix;
   installation-level keys are unchanged. The complete 18-key disposition table is a Phase 4 entry
   artifact reviewed with the code.
7. **Platform-settings read path (rev 4.3, F1; contract rev 4.4, F5).** Tenant-facing code never
   enters MODE_CENTRAL to read platform values. The Setting module exposes a narrow resolver — a
   Core contract, `PlatformSettingsInterface::get(string $name): mixed` over an **allowlisted**
   set of platform names (FX provider + key, platform identity) — implemented as a direct,
   indexed read of the NULL-tenant rows (cached `platform:setting.{name}`), consumed by the
   runtime of item 1 and by `ResolvesFxSettings`. The contract separates the two kinds of platform
   value: **config values** live in NULL-tenant `setting_settings` rows and come from this
   resolver; **reference data** — the platform base currency above all — lives where it always did
   (`core_currencies.is_default`, read through the Core gateway, §6.7.1) and never in a settings
   row. A missing or malformed platform setting throws `PlatformSettingMissing` (reported, 500) —
   it never silently becomes another store's value or an arbitrary application default. Precedence:
   a tenant value overrides the platform default; reference data is platform-level; enabled/default
   selections are per store. A store request therefore combines platform defaults (via the
   resolver) with tenant settings (via the scope) — central mode is never involved. Tests:
   rows 81, 93.
   **Cache invalidation (rev 4.5, F7):** platform-setting writes invalidate
   `platform:setting.{name}` in the same write path that updates the row (the super-admin
   platform settings action) — acceptance: the next request serves the new value (the
   worker-restart scenario of the Octane era is withdrawn with the runtime; under php-fpm this
   holds by construction).

#### 6.7.1 Money, languages, settings (P2, P5)

- **Platform base currency:** `core_currencies.is_default` keeps exactly one row and from now on
  means *the platform base currency*; every stored amount is in it; `exchange_rate` and
  `currencies:sync-rates` stay platform-wide. (rev 4.4, F1) at-most-one is database-enforced by
  the `core_currencies_one_default_unique` partial index (§4.5); at-least-one is established by
  the seeders and by provisioning; default changes are transactional. This flag — not a settings
  row — is the owning representation of the base currency; the §6.7 resolver reads config values
  only.
- **Per store:** default display currency and enabled currencies (`tenant_currencies`), default
  language and enabled languages (`tenant_languages`), `tax_classes.is_default` (scoped by
  `tenant_id`; at most one default per store is database-enforced by the
  `tax_classes_one_default_unique` partial index, §4.5), document prefixes (§6.9).
- **Settings (P5):** `setting_settings` keeps its enum list of names. Platform-wide names move to
  NULL-tenant rows (FX provider + `fx_api_key`, platform identity); everything else is per store.
  A store admin's settings screens read and write store rows only; platform rows are reachable
  only through super-admin routes under `CentralOperations`.
- **Transaction-time currency identity (rev 4.3, P2 condition).** Order, invoice, credit-note,
  refund and payment rows keep the currency and exchange rate captured when they were written
  (presence verified in Phase 2 step 0; added there if missing). A later change of the platform
  base currency never reinterprets historical amounts; cross-currency reporting converts
  explicitly.
- **Default lifecycle guards (rev 4.5, F3).** The at-least-one invariants are enforced by the
  mutation actions, transactionally: deleting (or disabling) the default tax class, a tenant's
  default currency, or unsetting the platform base currency is **rejected unless a replacement is
  selected in the same transaction**. Seeding alone does not protect the lifecycle. Test: row 106.

#### 6.7.2 Console command run modes

Every command that touches a store-owned table declares one mode; an architecture test
(dispatch-registry style) fails an undeclared one.

| Mode | Behaviour | Commands |
|---|---|---|
| `platform` | touches installation-level tables only | `sanctum:prune-expired`, `auth:prune-sign-in-lockouts`, `core:queue-check-backlog`, `queue:prune-failed`, `currencies:sync-rates` |
| `central` | a registry operation in `CentralOperations`; reads across stores, writes only installation-level data or rows stamped from their source row | `catalog:search:reconcile`, `catalog:reindex`, `catalog:search:install`, `catalog:search:cleanup`, `media:outbox:process` (bootstrap only), `tenancy:audit` |
| `tenant-fan-out` | iterates active stores and runs the body per store under `runForTenant` | `order:cancel-expired`, `order:sweep-generating-documents`, `order:reconcile-refund-credit-notes`, `webhooks:prune`, `media:regenerate-variants` |
| `bootstrap` | central lookup by primary key, then all work under `runForTenant` | `webhooks:dispatch-deliveries` (§6.2) |
| `discovery+fan-out` | a registered central operation performs read-only cross-store discovery and returns `(tenant_id, record id)` work items; every cleanup item executes under `runForTenant` | `media:outbox:sweep` (rev 4.8, B2) |

**`media:outbox:sweep` — two-stage execution (rev 4.8, B2; supersedes the rev 4.7
classification).** A globally scheduled command never acquires permission to write tenant-owned
rows. **Stage 1 — central discovery:** a registered operation reads across stores
(`media_media_outbox` non-terminal staging references, `media_object_locks` expired leases,
`media_deletion_intents` terminal/intent pruning candidates, retired trash keys) and returns
**work items only** — `(tenant_id, record id, kind)` tuples; central mode performs reads, never
writes. **Stage 2 — tenant-scoped cleanup:** each item executes under
`runForTenant($tenantId)`: the record is **reloaded and validated** (scoped model or guarded
query) before any update or delete, and filesystem cleanup (staging/trash unlink) follows the
same ownership + generation checks of §6.4. The architecture test fails any globally scheduled
command that writes a store-owned row directly. **Stage-2 contract (rev 4.9, per review):**
discovery results are **hints, not authorization** — every item is reloaded under tenant scope,
its ownership must match the work item's tenant id, and its current state and lease expiry are
revalidated (discovery-time state may already be stale); a stale work item is a safe no-op;
filesystem cleanup uses **only the exact key stored on the validated tenant-owned record**,
never a path supplied by the discovery result; one tenant's cleanup failure never blocks the
other tenants' work items. `discovery+fan-out` stays a narrowly registered operation with its
authorized call sites — not a general-purpose licence for central discovery plus arbitrary
tenant writes.

### 6.8 Search (rev 4.2, P1) — replaces the rev 4.1 §9.7 row "any future search index…"

Meilisearch exists and is installation-wide today (F40). Under P1:

- **Documents** gain `tenant_id`, copied from the product row by the index writer; `tenant_id`
  joins `filterableAttributes`.
- **Primary key stays `product_id`.** Product ids come from one database sequence, so they are
  unique installation-wide; the journal, cursor, lease and heartbeat tables (installation-level,
  §4.4) need no change.
- **One choke point, fail closed.** Every search goes through `SearchClientInterface`; the
  implementations build the filter `tenant_id = {id}` from `TenantContext::tenantId()`
  unconditionally and throw in `MODE_INACTIVE` and `MODE_CENTRAL` — the same rule as
  `TenantScope`. No calling code can compose a search without the filter; a query that lacks a
  tenant is never sent. Eloquent hydration (`PublicProductQuery::hydrationQuery()`) stays as a
  second line but is not the isolation boundary: without the filter, `total` and facets would
  still count other stores' products.
- **Writers** read the product under `runForTenant(<the product's tenant>)` — the tenant comes
  from a narrow central lookup by primary key, the §6.2 pattern — so snapshot building is a scoped
  read.
- **Gate and generation stay installation-wide:** one cutover for all stores. A reindex or a
  cutover is therefore a platform event, not a per-store one.
- **Commands:** `catalog:reindex` is registered and scheduled nightly (the operations runbook
  already calls it the absolute backstop); `catalog:search:install`, `catalog:search:cleanup` and
  `media:regenerate-variants` are registered (K2). Modes per §6.7.2.
- **Alternative, not recommended:** one index per store (`{uid_base}_t{tenant_id}_g{n}`). Isolation
  would not depend on a filter, at the cost of N indexes, per-store cutover, cleanup and
  reconciler loops, and an index per store at onboarding.
- **Stale-write and recovery guarantees (rev 4.3, F3).** `tenant_id` rides the existing fencing —
  no new mechanism. Journal entries are append-only and per-product ordered under the cursor
  lock-ordering invariant (postgres-migration §3.4.1b), so an older snapshot cannot overwrite a
  newer product state, and a stale queued write cannot resurrect a deleted product (terminal
  tombstone + retired-ID registry fail closed, §3.4.1a). The tenant field is stamped from the
  product row at journal-append time, so a document can never carry a tenant its row did not have;
  a tenant-lookup failure in a writer throws **before** any index write (fail closed — the same
  rule as the client). The reconciler's convergence pass (§3.2; SLO ≈ 2 × interval) diffs the
  journal against the serving generation and treats a **missing or mismatched `tenant_id` as a
  repairable stale document**, repaired through the same re-snapshot path as any stale content —
  the nightly reindex stays the backstop, not the primary recovery.
- **Tenant ownership is immutable (rev 4.4, F4; enforcement rev 4.5 B6, portable rev 4.6 B2).** A
  product row's `tenant_id` never changes after creation — and the same holds for every store-owned
  row. Enforcement is layered, because query-builder writes skip model events: the trait's
  `updating` guard plus no mass assignment (§2), the §4.3 per-query allowlist rejecting bulk
  updates that touch `tenant_id`, and a **database trigger, implemented per engine** on every
  table that carries `tenant_id` (the 85 of §4.4 — direct SQL included):
  - **PostgreSQL**: one shared trigger function
    `baxela_forbid_tenant_id_change()` (`BEFORE UPDATE … IF NEW.tenant_id IS DISTINCT FROM
    OLD.tenant_id THEN RAISE EXCEPTION`), attached per table with
    `CREATE TRIGGER {table}_tenant_immutable BEFORE UPDATE ON {table} FOR EACH ROW EXECUTE
    FUNCTION …`.
  - **SQLite** has no trigger functions: each table gets a per-table body,
    `CREATE TRIGGER {table}_tenant_immutable BEFORE UPDATE ON {table} FOR EACH ROW WHEN
    NEW.tenant_id IS NOT OLD.tenant_id BEGIN SELECT RAISE(ABORT, 'tenant_id is immutable');
    END;`.
    Both forms live behind explicit driver branches in the same migration edits that add
    `tenant_id`, and migration tests assert on both engines that every expected trigger exists
    and that a direct `UPDATE … SET tenant_id` is rejected (rows 101, 113).
  - **The nine pivots have no `tenant_id` column, so no trigger applies.** Their cross-tenant
    strategy is stated honestly rather than papered over: application-layer
    `AssertsTenantOwnership` validates both parent ids in the same transaction as every pivot
    write (§4.3 layer 2), the per-query allowlist covers direct SQL on pivot tables (layer 0),
    and `tenancy:audit`'s relation map joins through every pivot and reports owner/reference
    tenant mismatches (layer 4). **Threat-model boundary (rev 4.7, F2):** the guarantee is
    **preventive** for application writes through the approved pivot APIs, **allowlist-enforced**
    for direct SQL issued by application code, and **detective only** for SQL issued outside the
    application — external SQL is explicitly outside the preventive threat model, and `tenancy:
audit` is detection, not prevention. Direct SQL from within the app **can** still create a
cross-tenant association on a pivot only by escaping the per-query allowlist — the same
accepted module-boundary trade-off as R4, stated with its boundary.
  If a store-to-store move is ever genuinely needed, it must be a fenced migration operation that
  journals an ordered removal for the old tenant and an insertion for the new one under the
  existing cursor-lock invariants — not an ordinary update. Tests: rows 92, 101, 113.
- **Tests:** §10 rows 55–57, 80, 92.

### 6.9 Order documents (rev 4.2)

- **Sequences per store:** `order_document_sequences` becomes `UNIQUE(tenant_id, type, year)`;
  `DocumentNumberService` locks the row for `(tenant, type, year)` (its `lockForUpdate` path is
  otherwise unchanged). Document numbers (`invoices.number`, `credit_notes.number`) are unique per
  store (class T, §4.5).
- **Order codes stay installation-wide** (`orders.order_code`, class S, §4.5).
- **Prefix:** a per-store setting with the platform default as fallback, in place of the single
  `config('order.documents.prefixes.{type}')` value.
- **Seller identity and year:** read from the store runtime (§6.7) at generation time — in a job
  that is the job's tenant. The year already comes from `StoreTimezone::year()`, which becomes the
  store's year once the runtime is per tenant.
- **Files:** `tenants/{tenant_id}/documents/{number}.pdf` on the `local` disk; downloads resolve
  through scoped models, so store B cannot fetch store A's file even given the file name.
- **Sweeps:** `order:cancel-expired`, `order:sweep-generating-documents`,
  `order:reconcile-refund-credit-notes` run as tenant fan-out — each store's timezone decides what
  "expired" means.
- **Order-code lookup contract (rev 4.3, F6).** `order_code` is installation-unique and ordinary
  order queries stay tenant-scoped, so exactly one narrow workflow may resolve a code across
  stores: `ResolveOrderCodeAction` — (1) resolve the code to `(id, tenant_id)` only, via a
  registered central read (`order.lookup`, §6.5 Category 1); (2) authorize the requester:
  platform super-admin; the order's customer (global user-id match, no tenant needed); or store
  staff — active membership in the order's tenant, the order-view permission, and the resolved
  tenant equal to the caller's active tenant (rev 4.5, F5 — staff never resolve other stores'
  codes) — anything else stops here with 404; (3) `runForTenant($tenantId)` and load the order under the
  full scope for the staff-side continuation; (4) return only the fields the audience may see. The
  workflow discloses nothing beyond "this code exists" to a requester who already knows the code
  and is the customer or a super-admin.
- **Tests:** §10 rows 53–54.

### 6.10 Transfer and export (rev 4.2)

- **Per-store rows:** `transfer_imports` and `transfer_remaps` gain `tenant_id` (the destination
  store); the remap unique becomes `(tenant_id, source_installation, module, entity, source_id)`
  (class T). `transfer_installation` stays one row per installation and `core_transfer_locks`
  stays installation-level; the lock name becomes `transfer:import:{tenant_id}:{module}`, so two
  stores can import the same module at once.
- **A cross-store import is a remap import.** Importing store A's export into store B creates B's
  rows. The raw-id shortcut (same installation → reuse ids) is valid only when the source tenant
  equals the destination tenant; otherwise it is refused. Lease, fencing and the savepoint rules of
  the PostgreSQL plan are unchanged — they belong to the import session.
- **Settings export (P5):** a store admin's export contains store rows only — never platform
  settings and never credentials (payment credentials, `fx_api_key`). Today `SettingTransfer`
  exports everything including the `fx_api_key` ciphertext (F43). A platform-level export, if it is
  kept at all, is a super-admin `CentralOperations` operation.
- **Blanket update removed:** `DiscountTransfer.php:537-539` updates every coupon row with no
  `WHERE` (F43); with several stores that is a cross-store write. It gets a tenant clause (K5).
- Exports are streamed (F43); artifact storage only per §6.4.
- **Tests:** §10 rows 58–60.

### 6.11 Extensions (rev 4.2, P3)

`core_extensions` is installation-level, but its toggle routes are reachable by store admins
(F44). Install / uninstall / toggle move to super-admin-only platform routes, the
`core.admin.extensions.*` permissions are removed from tenant roles, and `core.extension-updates`
stays a platform cache. A per-store enable switch is out of scope and recorded in §9.7.

### 6.12 Notifications, caches, rate limits (rev 4.2)

- **Notifications:** templates take store name and storefront URL from the tenant snapshot at
  render time, inside the tenant run (rev 4.1 §9.7) — `config('app.name')` (F47) is no longer the
  source. Admin recipients are the store's staff, read under the scope (the team-aware query of
  §5). The 24 queued listeners carry the payload stamp (§6.1); the broadcast channel rename is
  rev 4.1's realtime row.
- **User-level tables:** `notification_preferences`, `notification_push_subscriptions`,
  `notification_telegram_subscriptions` stay installation-level (per user); a Web Push payload
  names the store it belongs to.
- **Caches** — rule in §6.7 item 6. Store-dependent: `setting.*` (timezone, identity, fx,
  prices-include-tax), `payment.credentials`, `payment.paypal.access_token`,
  `core.language.default_id`, `catalog.public.sitemap`, `content.public.sitemap`.
  Installation-level: `telegram:bot:username`, `core.extension-updates` (+ `.stale`),
  `core.regions.{CC}`, `catalog.search.reconcile.failures`, the Google JWKS cache. Locks keyed by
  a globally unique id (`media-variants:{id}`, `webhook-endpoint:{id}`) need no tenant because ids
  never repeat across stores; `sign-in-gate`, `core.fx.sync` and the JWKS refresh lock are
  platform.
- **Rate limiters:** `cart-public`, `contact-submit`, `contact-subscribe`,
  `support-ticket-create`, `support-ticket-reply` are keyed by tenant + ip; `webhook` by tenant
  slug + driver; `auth-*`, `notification-webhook` and `machine` stay platform.
- **Tests:** §10 row 45, 63–65.

---

## 7. Host contract — one definition, no ambiguity

The storefront browser talks cross-origin to the API, so the API's `Host` is the *API* host, never
the storefront host (F17). Contract:

- **`X-Tenant-Host`** — the storefront host (`acme.example.com`), set by the storefront client
  (`location.host`) and SSR fetches (`headers()` in `lib/api/server.ts` + `client.ts`). Honored on
  public/user routes only. Spoofing it only selects which *public* store you browse — equivalent to
  visiting that domain; no privilege gained. Never honored on admin routes.
- **`X-Tenant-Id`** — admin audience only, membership-enforced (§3.2).
- **`Host`** — always consulted (API-on-tenant-domain deployments).
- Precedence as in §3.1; `X-Forwarded-Host` is **not** trusted (nginx passes `Host` directly; no
  proxy chain assumption changes).

`apps/storefront/proxy.ts` stays next-intl-only; host forwarding happens in the API client layer
(one chokepoint per F17), plus `lib/site-url.ts` derives per-request origin from `headers()` /
tenant snapshot for metadataBase, sitemap, robots.

---

## 8. Phased delivery — files, order, acceptance criteria

Heavy commands are sequenced and `nice -n 10`-prefixed (4-core machine); `composer analyse` before
Pest in each phase; `vendor/bin/pint` on touched paths; full `composer test` once per phase —
the SQLite suite, plus the PostgreSQL suite against the dev stack.
Phase-by-phase approval: each phase ends with a changed-file list + analyse/test output for review
before the next phase starts.

### Phase 1 — Tenancy core + Tenant module (backend)

Files (new): `Modules/Tenant/**` (full module per §4.1, incl. the `onboarding`-audience endpoint
stub — §3.1 — wired in
Phase 7); `Modules/Core/app/Support/Tenancy/{TenantContext,CentralOperations,TenantSnapshot}.php`,
`Modules/Core/app/Models/Scopes/TenantScope.php`, `Modules/Core/app/Models/Traits/TenantScopedTrait.php`,
`Modules/Core/app/Schemas/Shared/TenantScopedTrait.php`,
`Modules/Core/app/Http/Middleware/{ResolveTenant,AuthorizeTenantAccess}.php`,
`Modules/Core/app/Contracts/Gateways/Tenant/**`, the `TenantResolved` event contract, queue
lifecycle listeners (snapshot stack), the central-mode architecture test, and tenant
log-context/Sentry tagging (§9.7 logging, rev 4.1 assignment). `Modules/Core/lang/*/errors.php`
keys (`tenant_not_found`, `tenant_suspended`, `tenant_membership_required`,
`tenant_context_missing`, `tenant_selection_required`; `idempotency_in_progress` lands with
Phase 4). (rev 4.4) `NormalizeTenantRequest` (Stage 1) and the revised Stage-2
`ResolveTenant` with the middleware priority list (§3.1). (rev 4.5) the tenant-neutral
`onboarding` audience + its route allowlist (§3.1), webhook slug resolution in Stage 2 (§3.1),
and the typed `CentralOperation` interface + handler registry (§6.5). Modified:
`bootstrap/app.php`
(global prepend of the Stage-1 normalizer + `$middleware->priority([...])`, §3.1), the
`personal_access_tokens` migration (nullable `tenant_id`, B2), `CoreServiceProvider` (payload
stamping + listeners), `CoreDatabaseSeeder`.
`config/permission.php` untouched this phase.

Tests: resolution matrix (rows 5, 8 public/user cells, 14), scope fail-closed unit tests (row 12),
context snapshot/restore incl. nested `runForTenant` (row 20), queue lifecycle in **both models**
(sync inline + `queue:work --once`, rows 26–28), tenant/domain/membership CRUD + non-super-admin
403, `CentralOperations` registry/reason/exception-safety + architecture test (rows 21–22),
default-tenant seeder assertion, (rev 4.2) effective middleware order, (rev 4.3) route-table
enumeration — every API route carries a classified audience or
the test fails (row 82), (rev 4.4) behavioral lifecycle tests — the audience decision happens
after route match, the context before binding and credential application, proven on real requests
through the priority-sorted stack (row 83) — machine-token Stage-2 resolution incl.
ordinary/revoked/header-override cases (row 84) and the provisioning write guard (row 94),
(rev 4.5) tenant-neutral onboarding from the central host (row 95), webhook slug resolution
(row 96), unauthorized central-operation call site (row 98), quota-boundary concurrency on
PostgreSQL (row 105), suspended-tenant machine denial (row 108), and priority/defaults stack
assertions across representative routes (row 110), (rev 4.6) exact-class central-operation
binding (row 115), (rev 4.7) queue payloads cannot grant central mode (row 118).

**Accept**: new tests green; **entire existing suite green with zero edits** (loopback fallback);
`composer analyse` clean; seeder produces default tenant + domain + owner membership.

### Phase 2 — Data scoping (backend bulk) — A1 confirmed; in-place migrations approved

Step 0 (rev 4.2, entry artifact reviewed before any code change): the authoritative table and
unique-index lists are dumped from the migrated database (`information_schema.columns`,
`pg_indexes`) and diffed against §4.4 and §4.5; any difference is settled in this plan first.
**This is a hard gate (rev 4.3, per review): no Phase 2 code change starts until the diff is empty
or every difference is settled here.**

**Step 0 reconciliation (recorded 2026-11-27).** The dev database was recreated from the current
working-tree migrations (`migrate:fresh`) and dumped with `information_schema.columns` and
`pg_indexes` before any further Phase 2 edit. Results:

- 136 physical base tables, of which `migrations` is Laravel bookkeeping outside the inventory;
  135 classified = 91 store-owned (82 with `tenant_id` + 9 pivots) + 39 installation-level + 5
  Tenant metadata. The final 138 = these 135 + 3 Phase-4 media coordination tables; no classified
  table is missing.
- 87 `tenant_id` columns: 82 store-owned NOT NULL, 3 nullable (`audit_logs`,
  `personal_access_tokens`, `setting_settings`), 4 Tenant metadata NOT NULL; all BIGINT with
  `tenant_id >= 0` checks and indexes. No unexpected column; the five Phase-3 spatie team keys and
  the Phase-4 media tables are absent by design.
- 100 entry-dump non-primary-key unique indexes = the 90 §4.5 dispositions + 4 added partials
  (`core_currencies_one_default_unique`, `media_folders_root_name_unique`,
  `setting_settings_name_platform_unique`, `tax_classes_one_default_unique`) + 6 Tenant-module
  uniques. Phase 2's layer-3 composite FKs add 11 supporting `(tenant_id, id)` parent uniques, so
  the final dump has 111. The three R indexes remain in their pre-redesign shape (Phase 4/5).
  The index differences below are settled by correcting this plan's names, not by changing the dump.
- Name corrections made here: §4.4/§4.5 `core_transfer_installation` → `transfer_installation`;
  §4.5 `payment_methods.code` → `payment_methods.method`; §4.5 `job_batches.uuid` →
  `failed_jobs.uuid` (the live key is `failed_jobs_uuid_unique`). §4.1's `domain` key is the
  partial `WHERE status <> 'released'` index recorded there.
- The provisional immutability objects in the entry dump are `prevent_tenant_id_change()` and
  `{table}_tenant_id_immutable`; Phase 2 uses the §6.8 names
  (`baxela_forbid_tenant_id_change()` / `{table}_tenant_immutable`) before final verification.
  Semantics are unchanged. The `enum(...)` columns of §4.1 are stored as strings with
  application-level enums, matching the existing schema style.
- §6.7.1 transaction-time currency identity is not present in the entry dump: `order_orders` has
  `currency_id` + `display_currency_code`/`display_fx_rate`; invoices and credit notes have
  `currency_code` but no rate; payments have `currency_id` but no rate/code snapshot; refunds have
  neither. Phase 2 adds the missing snapshots in place, as §6.7.1 requires.
- Early tenant stamps on `core_idempotency_keys`, `payment_driver_credentials`,
  `media_media_outbox`, `transfer_imports`, and `transfer_remaps` are kept: their final §4.4
  classification is store-owned and adding the column during the bulk schema pass avoids rewriting
  the original migrations twice. Their redesign-specific columns and indexes stay in Phases 4/5.

The hard gate is settled: no §4.4/§4.5 difference remains unresolved.

Per-module batches (edit migrations in place + `use TenantScopedTrait;` on models + fillable +
factories + HelperTrait `seedTenant()`/`withTenant()`):

1. Commerce: Catalog (30 store-owned tables: 4 pivots, 9 translation tables), Inventory, Cart,
   Order (8 tables; document numbering itself lands in Phase 5).
2. Operations: Tax (`ScopedRateUniqueRule` + `UniquePair`/`LanguageUniquePair` tenant clause —
   closes K1), Shipping, Discount (pivot-sync `AssertsTenantOwnership` per §4.3; closes K5),
   Payment (4 tables; the resolver lands in Phase 4), Webhooks, Media (§6.4), Machine audience
   (closes K3, K4).
3. Content and configuration: Content, Menu, Setting (store rows + platform tier, P5),
   Notification (`notifications`), Audit (platform tier), Contact, `tenant_languages` /
   `tenant_currencies` (P2).
4. Engagement: User (wishlist; addresses and profiles stay global — rev 4.3, B4: the address
   book keeps user scoping only, staff read `order_addresses` snapshots), Support, Core
   (`idempotency_keys` schema change lands with Phase 4; `transfer_*` with Phase 5).
5. Close-out: every `DB::table` chain on a store-owned table gets a tenant clause or a
   `CentralOperations` wrap with a justifying comment (§4.3 layer 0); every `unique:` / `exists:`
   rule on a store-owned table becomes tenant-aware; intra-module composite FKs (layer 3);
   `tenancy:audit` command with relation map incl. pivot relations.

**Accept**: rows 1–4, 12–13, 19, 23, 31, 46–52, 68, 70, 73, 75, 85–87, 92, 101–102, 106, 113 pass via two seeded tenants; the §4.5
dispositions are visible in `pg_indexes`; builder-chain and validation-rule architecture tests
green; composite-FK violation test proves DB-level rejection of a cross-tenant intra-module
reference; `tenancy:audit` reports zero violations after seeding; suite green.

### Phase 3 — Auth & permissions

Files: `config/permission.php` (teams), permission-tables migration (in place, §5),
`AuthServiceProvider` Gate::before pivot check, `AuthorizeTenantAccess` team-id set/reset,
`SignInAction` memberships payload, admin default selection (§3.1, A5), role CRUD per-tenant
aware, role seeder per tenant; (rev 4.3) the `auth_roles_platform_unique` partial index (§5) and
the Category-2 tenant-operation membership actions (§6.5).

**Accept** (rev 4 additions per review): rows 6–7, 69, 72; platform (NULL-team) role vs tenant role
separation — identical role names coexist across tenants; role/team switching on consecutive
requests; **membership revocation takes effect on the very next request** (row 33); (rev 4.2)
row 67; existing super-admin factory flows green; (rev 4.5) rows 103–104; (rev 4.7) row 119.

### Phase 4 — Jobs, cache, webhooks, payments, idempotency, media, store runtime

Files per §6: queue stamping + listeners (if not already in Phase 1); `DeliverWebhookJob`
bootstrap branches + `TenantAware` middleware; outbox tenant columns; `payment_driver_credentials`
tenant columns + `PaymentConfigurationResolverInterface` + **full F25 client/driver/manager
refactor** (incl. atomic `credential_version` bumps and disabled-is-unusable enforcement, §6.3);
PayPal token cache scoping (§6.3); `POST /webhook/{tenant-slug}/{driver}` routing;
suspended-tenant job skip + audit; media store path (§6.4, P4); **store runtime per request
(§6.7)** incl. the seven resolver fixes (F38) and the `SettingServiceProvider::boot()` cleanup
(store runtime per request — the Octane-era K8 ceased with the runtime); the 18-key cache disposition table + `UpdateSettingAction`; **idempotency redesign per
§6.6** (NOT NULL `principal_key`, composite unique, atomic reservation, 409 in-progress);
**Reverb channel migration to tenant-scoped names + broadcasting authorization checks** (§9.7
realtime); **tenant-aware rate-limiter keys** (§9.7); **tenant-branded notifications** (§6.12);
`NotificationCreated` registered (K7); `MediaGateway::upsertLocal` actor (K6). (rev 4.3–4.4
additions: idempotency fencing per §6.6 — claim token, the abort-on-lost-ownership `committed_at`
marker, `idempotency_unresolved`, provider keys derived from the stable logical identity; the
platform-settings resolver per §6.7 incl. `PlatformSettingMissing`; the media per-key database
lease per §6.4.)

**Accept**: rows 9–11, 17–18, 24–25, 29–32, 42, 45, 61, 63–65, 76–79, 81, 88–89, 91, 93, 97, 99–100, 111–112, 114, 116–117, 120–123, 113 (the media coordination tables' triggers); concurrency test proves exactly
one of two parallel same-key requests executes (other 409 or replay); same key string reusable
across tenants; tenant signature routing accepts only the matching tenant secret;
credentials/token caches never cross tenants or credential versions; two stores served in
sequence by one process see only their own store runtime (§6.7).

### Phase 5 — Search, order documents, transfer, console commands (rev 4.2)

Files: §6.8 (index writer `tenant_id`, `filterableAttributes`, the fail-closed filter in the
search client, tenant lookup for writers, registration of `catalog:reindex` — scheduled nightly —
`catalog:search:install`, `catalog:search:cleanup`, `media:regenerate-variants`, closes K2);
§6.9 (`order_document_sequences` unique, per-store numbering, prefix setting, PDF path, tenant
fan-out sweeps); §6.10 (`transfer_imports` / `transfer_remaps` tenant columns, lock name,
cross-store import = remap tier, settings export split, `DiscountTransfer` fix); the console
command run-mode declaration + architecture test (§6.7.2); store-runtime isolation tests for
the queue-worker path.

**Accept**: rows 53–60, 66, 80, 107; a reindex over two seeded stores leaves no document without
`tenant_id`; two stores served in sequence by one process see only their own values; suite green.

### Phase 6 — Storefront

Files: `apps/storefront/lib/api/client.ts` (+ `server.ts`) forward `X-Tenant-Host`;
`lib/site-url.ts` per-request origin; sitemap/robots/metadataBase per host; payment return URLs
from tenant domains; settings/theme/menu per tenant; (rev 4.2) media and image URLs rendered with
the resolved store host (§6.4).
**Accept**: two local hosts (`a.localhost`, `b.localhost`) render different tenants end-to-end
through compose; row 15 (guest cart isolation); **host-spoofing tests** — forged/`X-Forwarded-Host`
combinations cannot switch a user-route tenant identity (row 14); customer account works across
stores (global user, per-tenant carts/orders); (rev 4.2) row 51.

### Phase 7 — Admin panel

Files: `apps/admin/src/stores/tenant-store.ts`; `api-client.ts` `X-Tenant-Id` interceptor;
sign-in memberships; real `team-switcher.tsx` (switch = set tenant + `queryClient.clear()` +
router refresh); `src/features/tenants/` CRUD + members + onboarding UI; six locale files (en + fa
complete); storefront deep-links from active tenant domain; payment gateway config UI
(per-tenant credentials + webhook secret + enabled toggle); (rev 4.2) the store switcher lists the
member's own stores only (A5); extension toggles leave the store admin (P3); (rev 4.3)
`ProvisionTenantAction` wiring (§6.5 Category 3) and the domain-verification UI (challenge
issue/verify, release).
**Accept**: row 16 verified by asserting refetch (not stale render) including rapid back-and-forth
switching (concurrent in-flight requests cancel/refetch — row 34); tenant CRUD + member management
+ onboarding round-trip (rows 71–72); payment config UI reads/writes only the active tenant;
locales en + fa; (rev 4.2) row 62; (rev 4.3) row 74; (rev 4.4) row 90.

### Phase 8 — Infra, Bruno, docs, security gate

Nginx passes Host already; document `*.localhost` dev hosts; `.env.example` gains
`TENANCY_FALLBACK_HOSTS`/`TENANCY_CENTRAL_HOSTS` (prod: empty) and payment vars marked
test-fallback-only; Bruno `Local.bru` tenant presets + `Tenant/Admin` folder; backend `AGENTS.md`
tenancy section (when to add `TenantScopedTrait`, `CentralOperations` registry rules, global-table
list, composite-FK rule, console run modes); update `docs/PLATFORM_GAPS_ROADMAP.md`; regenerate
module graph (`python3 scripts/generate-module-graph.py`, commit `docs(monorepo): regenerate
module dependency graph`); tenant operations runbook (provisioning, suspension, domain
verification, deletion, rate-limit tuning, search reindex).

**Final security acceptance gate** (rev 4, per review): an automated adversarial suite asserts
cross-tenant access is denied for products/categories, orders/payments, the customer address book
under the three rules of §2.1 (rev 4.3, B4), media
and exports, payment credentials, private realtime channels, background-job records, search
results and document files (rows 35–38, 54–55, 73), run against a production-like config (empty
fallback list).

**Accept**: fresh-clone bootstrap serves a working default-tenant storefront + admin with zero
tenant configuration; security gate green.

---

## 9. Additional risks, boundaries, and requirements

### 9.1–9.5 Risks

- **R1 — Platform-level services**: Telegram bot, Mail mailer, and Reverb app remain platform-level
  (A2 covers only payment gateways). Per-tenant mail sending identities (from-name/domain from
  tenant settings) ride the platform transport — §9.7 notifications.
- **R2 — Idempotency**: resolved by the §6.6 redesign (was: tenant column only).
- **R3 — Import/Transfer surfaces** (F9): high-risk raw-query sites; require `CentralOperations`
  or tenant-context wrapping plus `AssertsTenantOwnership` (Phase 2, Phase 5).
- **R4 — App-layer tenant integrity for cross-module references**: deliberate module-boundary
  trade-off; mitigated by scoped validation, in-transaction ownership asserts, intra-module
  composite FKs (§4.3), and the relation-map audit. Residual risk: an unreviewed raw write
  bypassing all layers; the architecture test and audit map must evolve with schema.
- **R5 — Shared search index (rev 4.2)**: isolation depends on one filter that one client always
  injects. Mitigation: the filter is not optional in the client API, a missing tenant throws, and
  Eloquent hydration is a second line. If the reviewer prefers isolation without a filter, P1
  flips to one index per store (§6.8) without changing any other section.

### 9.7 Missing requirements (rev 4, per review; phases renumbered in rev 4.2)

| Area | Requirement |
|---|---|
| Tenant deletion | Soft-delete is the default (storefront closes like suspension). Permanent deletion is a super-admin `CentralOperations` action with an audit reason: revokes memberships, detaches domains (after a configurable retention window), anonymizes rather than hard-deletes order/tax records where legally prudent. Retention/recovery window configurable; no automatic hard delete. |
| Domain management | Concrete protocol (rev 4.1, review finding 5; lifecycle persisted rev 4.3, B5): **verification is HTTPS-first with DNS TXT as the alternate** — the admin generates a 32-byte random token per domain attempt, stored **hashed** on the `tenant_domains` row (`verification_token_hash`) with `verified_at` null; the HTTPS challenge serves the token at `https://{domain}/.well-known/baxela-verification.txt` (plain HTTP only as an explicit non-production fallback), the DNS alternate is a `TXT` record `_baxela-verify.{domain}` containing the token. **Fetch protections (rev 4.3; rebinding closed rev 4.4, B5):** the verification fetch resolves both A/AAAA once, rejects private, loopback, link-local, unspecified and IPv4-mapped-IPv6 ranges, **then connects to a pinned, validated IP while preserving the hostname for TLS SNI and the HTTP Host header** (e.g. `CURLOPT_RESOLVE`) — the HTTP client never performs a second DNS lookup, and the actual peer address is validated after connect; no redirects are followed; short timeout; the token is compared in constant time. Test: row 90. Tokens expire after a configurable TTL (default 48 h; `verification_expires_at`) and an unverified/expired domain resolves nothing (§3.1 lookup requires `verified_at`). Loss of control: verified status is re-checked opportunistically on super-admin action and can be force-revoked; a **released domain enters a cooling-off window (default 7 days, configurable — longer than common DNS TTLs; `released_at` / `cooling_off_until`) during which it cannot be attached to a different tenant**, after which the new owner must complete their own verification. A domain is attachable to at most one tenant (unique index), at most one primary domain exists per tenant (partial unique index, §4.1), and reassignment always requires release + cooling-off + fresh verification. |
| Rate limiting | **Phase 4** (implementation): public/user rate limiters become tenant-aware (`by($tenantId.':'.$ip)`) so one tenant's traffic cannot exhaust another's quota; webhook limiter keyed by tenant slug + driver. **Phase 6** (verification): storefront-facing limiter isolation tested end-to-end (row 41). |
| Realtime | **Phase 4** (implementation, file-listed): channel names migrate to tenant scope (`private-tenant.{tenantId}.*`), the broadcasting authorization endpoint validates tenant identity + membership/customer status, and all `broadcasts()`/`Echo` channel references are updated with the rename; cross-tenant subscription by changing an identifier is denied (row 35). |
| Logging | **Phase 1** (implementation): `ResolveTenant` adds `tenant.id` to the request log context (extending the `AssignRequestId` pattern) and tags the Sentry scope when the SDK is active; logs never include credentials (Audit redaction, §6.3). Acceptance: log-context assertion in the Phase 1 middleware tests. |
| Exports | Exports are streamed today (F43); any stored artifact goes under `tenants/{id}/` and is downloadable only through tenant-scoped authorization; export listings are tenant-scoped (row 37). Phases 5 (§6.10) and 8 (security gate). |
| Notifications | **Phase 4** (implementation): notification mailables/listeners render tenant identity (store name, from-name, storefront URL from tenant settings — resolved at send time inside the tenant run); transport stays platform-level (R1). Acceptance: notification content test asserts the owning tenant's branding (row 63). |
| Tenant onboarding | Self-serve workflow (**`onboarding` audience** — tenant-neutral, §3.1; rev 4.6, B4: the canonical route is `POST /api/v1/onboarding/tenants`, named `api.tenant.onboarding.tenants.store` — earlier `user`-audience wording is superseded everywhere): sign up → `POST /api/v1/onboarding/tenants` (rate-limited; authenticated global user, no resolved tenant required) → creator becomes owner (active membership + owner role for their tenant) → `<slug>` subdomain assigned + seeded default settings/menu → invite members. Super-admin CRUD remains for platform operations. Phase 7. (rev 4.3, B1: executed by `ProvisionTenantAction` — §6.5 Category 3; single transaction, onboarding limits, no central mode; test row 71.) |
| Search | (rev 4.2) Meilisearch is in production scope: §6.8, Phase 5. |
| Backups | Per-tenant data export via the Transfer standard (already tenant-scoped); per-tenant restore is a follow-up decision, not in scope. |
| Machine audience (rev 4.2; design rev 4.4, B2; lifecycle rev 4.5, F6) | Machine tokens are issued for one store — the tenant id is stored on the `personal_access_tokens` row at issue time (a nullable column; ordinary user tokens stay NULL, §4.4) — and resolved in Stage 2 by validating the token and reading its tenant before any binding (§3.1); machine routes ignore every tenant header. Lifecycle rules (rev 4.5): the token's `tenant_id` is **immutable after issuance**; ordinary token issuance always leaves it NULL; only the dedicated machine-token issuance action may set it; "revoked" means row deletion (Sanctum semantics — no extra column); machine tokens are issued with **explicit abilities** the machine routes check; machine requests against suspended or soft-deleted tenants are denied before any tenant-scoped work (row 8 includes the machine audience, row 108); permanent tenant deletion revokes its machine tokens. Today `MachineTokenRequest` validates against a nonexistent `users` table (K3) and the machine product listing has no ownership filter (K4). Phase 1 (column + Stage-2 resolution), Phase 2 (K3/K4). |
| Multi-store owners (rev 4.2) | A5: one store at a time; the admin switcher lists the member's own stores; no combined view. Phase 3 (selection rule), Phase 7 (UI). |
| Per-store extensions (rev 4.2) | Out of scope under P3; recorded as a possible follow-up if a store ever needs its own switch. |

### 9.8 Known defects found by the inventory (rev 4.2) — pre-existing, fixed only where a phase touches the code

| # | Defect | Fixed in |
|---|---|---|
| K1 | `Modules/Core/app/Rules/LanguageUniquePair.php`: the ignore-id clause is added on top of the equality clause, so on update the query can never match — uniqueness is not enforced on update. | Phase 2 (the rule is rewritten tenant-aware) |
| K2 | `catalog:reindex`, `catalog:search:install`, `catalog:search:cleanup`, `media:regenerate-variants` are not registered as commands; `catalog:reindex` is not scheduled although the runbook calls it the nightly backstop. | Phase 5 |
| K3 | `MachineTokenRequest` validates against a nonexistent `users` table. | Phase 2 |
| K4 | The machine product listing has no ownership filter. | Phase 2 |
| K5 | `Modules/Discount/app/Transfer/DiscountTransfer.php:537-539` updates every coupon row (no `WHERE`). | Phase 2, Phase 5 |
| K6 | `MediaGateway::upsertLocal` hard-codes `USER_ID => 1`. | Phase 4 |
| K7 | `NotificationCreated` (`ShouldBroadcast`) is outside the dispatch registry. | Phase 4 |
| K9 | `catalog_images.url` stores absolute URLs with the installation host. | Phase 4 (storage), Phase 6 (rendering) |
| K10 | Two root media folders can share a name (`[name, parent_id]` unique with NULL parent). | Phase 2 (partial unique index on `(tenant_id, name) WHERE parent_id IS NULL`, §4.5) |

---

## 10. Isolation test matrix (Phase-gated, in `Modules/Tenant/tests/Feature/Isolation/` + module suites)

| # | Scenario | Expected | Phase |
|---|---|---|---|
| 1 | Tenant A creates product | belongs to A | 2 |
| 2 | Tenant B lists products | sees none of A's | 2 |
| 3 | A fetches B's product by id | 404 | 2 |
| 4 | A updates / deletes B's product | rejected | 2 |
| 5 | Unknown host (prod-like config) | 404 envelope | 1 |
| 6 | User switches via `X-Tenant-Id` without membership | 403 | 3 |
| 7 | Super-admin switches via header | allowed | 3 |
| 8 | Suspended tenant matrix: public/user; active member admin; platform super-admin admin; queued jobs; machine | public/user and active member admin → 403; super-admin full access/reactivate; queued jobs skipped + audit logged; machine denied before any scoped work | 1/3/4 |
| 9 | Queued job runs for A | touches only A's rows | 4 |
| 10 | Cache read under A then B | no shared values (sitemap, settings keys) | 4 |
| 11 | Same idempotency key on A then B | no cross-replay; both usable independently | 4 |
| 12 | Tenant context missing (unit) | `TenantContextMissingException`, no query | 1 |
| 13 | Identical product name/slug in A and B | both allowed (tenant-scoped uniques) | 2 |
| 14 | `X-Tenant-Id`/`X-Forwarded-Host` on a public/user route | ignored — host identity wins | 1/6 |
| 15 | Guest cart token from A used against B | no access | 6 |
| 16 | Admin tenant switch (incl. rapid back-and-forth) | query cache cleared, lists refetch, no stale cross-tenant render | 7 |
| 17 | Payment webhook (signed, tenant slug route) | writes land in that tenant only | 4 |
| 18 | Media upload under A | stored under A's prefix, invisible to B | 4 |
| 19 | Import/export run per tenant | never crosses tenants | 2 |
| 20 | Context restore after nested `runForTenant` / central ops | previous mode exact-restored (incl. on exception) | 1 |
| 21 | Non-super-admin calls a central-mode route | 403; context unchanged | 1 |
| 22 | `CentralOperations::run` with unregistered operation / missing reason | throws; audit records nothing but the attempt logs | 1 |
| 23 | `tenancy:audit` sees a planted cross-tenant relation (direct + via pivot) | reports owner/reference tenant mismatch | 2 |
| 24 | Webhook signature with wrong/other tenant secret | rejected before any write | 4 |
| 25 | Payment config isolation | A cannot read/use B's credentials, secrets, or token cache | 4/7 |
| 26 | Sync (inline) dispatch inside a tenant request | job runs scoped; **request context intact afterward** | 1 |
| 27 | Real worker (`queue:work --once`, database driver) | context init from payload; restored between jobs; residue from a prior job cannot leak | 1 |
| 28 | Job dispatched with no tenant markers touches a scoped model | throws `TenantContextMissingException` | 1 |
| 29 | Delayed/retried job | payload markers persist; every attempt re-initializes | 4 |
| 30 | Two parallel requests, same tenant+user+key | exactly one executes; other gets replay or 409 in-progress | 4 |
| 31 | Same key string, different tenants (composite unique) | both succeed independently | 4 |
| 32 | PayPal token cache after credential rotation | new `credential_version` → fresh token, no reuse | 4 |
| 33 | Membership revoked mid-session | next request 403 (no stale authorization) | 3 |
| 34 | Rapid tenant switching in admin (concurrent in-flight requests) | responses never mixed across tenants | 7 |
| 35 | Cross-tenant private channel subscription | denied by channel authorization | 4 |
| 36 | Intra-module composite FK cross-tenant insert | rejected at DB level | 2 |
| 37 | Export artifact of A downloaded under B | denied | 2/8 |
| 38 | Security acceptance sweep (prod-like config) | all resource classes listed in the Phase 8 gate deny cross-tenant access | 8 |
| 39 | Two simultaneous **guest** requests, same tenant + key + cart token | exactly one executes; other gets replay or 409 (NOT NULL `principal_key` closes the NULL-duplicate hole) | 4 |
| 40 | Nested sync jobs pinned to **different tenants** unwind in order | each `JobAttempted` pops its own snapshot; outer request/tenant context exact-restored | 1 |
| 41 | Rate limiter keyed per tenant | tenant A's saturation does not throttle tenant B | 4/6 |
| 42 | Disabled payment method invoked via direct driver/client call | resolver throws; no credential use | 4 |
| 45 | Tenant exception raised inside a `catch (Throwable)` resolver | rethrown — not turned into a platform default | 4 |
| 46 | Same SKU / barcode / coupon code / newsletter email in A and B (case-insensitive) | both allowed | 2 |
| 47 | Two root folders with the same name in one store | rejected | 2 |
| 48 | Bulk insert / `upsert` without `tenant_id` on a store-owned table | rejected by the database | 2 |
| 49 | `DB::table` chain on a store-owned table outside the registered list | architecture test fails | 2 |
| 50 | `unique:` / `exists:` validation under store A with a matching row in B | counts A's rows only | 2 |
| 51 | Same slug in two stores, requested on each store's host | each storefront returns its own product | 2/6 |
| 52 | Settings: platform tier | one platform row per name; one row per store per name | 2 |
| 53 | Order codes and document numbers | order codes unique across stores; the same document number may exist in A and B; sequences independent | 5 |
| 54 | Document PDF of store A requested under store B | denied | 5 |
| 55 | Search under A with a matching product in B (identical slug/SKU) | only A's product returned; `total` counts A only | 5 |
| 56 | Search request without tenant context | throws; no unfiltered query is sent | 5 |
| 57 | Reindex / reconcile over two stores | every document carries its `tenant_id`; none missing after cutover | 5 |
| 58 | Store A's export imported into store B | B's rows created; A's rows untouched; raw-id shortcut refused | 5 |
| 59 | Store-admin settings export | contains no platform settings and no credentials | 5 |
| 60 | Two stores import the same module concurrently | both proceed — the lock is per store | 5 |
| 61 | Identical file uploaded by A and B, then deleted by A | two objects; B's file intact | 4 |
| 62 | Store admin calls an extension install/uninstall/toggle route | 403; super-admin succeeds | 7 |
| 63 | Notification for a store B order, rendered after a store A job on the same worker | carries B's name and URL | 4 |
| 64 | Every store-dependent cache key written under A | not read under B | 4 |
| 65 | Store A saturates `contact-submit` | store B is not throttled | 4 |
| 66 | Console command without a declared run mode | architecture test fails | 5 |
| 67 | Admin request on a central host without `X-Tenant-Id` | one membership → selected; several → 422 with the list; none → 403 | 3 |
| 68 | Machine route with a token issued for store A | sees A's rows only; headers cannot change the store | 2 |
| 69 | Duplicate NULL-team platform role row | rejected by `auth_roles_platform_unique` at the database level (both engines) | 3 |
| 70 | Full migration run on both planes | SQLite suite and PostgreSQL suite both migrate cleanly (driver-branch coverage) | 2 |
| 71 | Normal user completes onboarding | tenant + owner membership + defaults committed atomically; central mode never entered; onboarding limits enforced | 7 |
| 72 | Tenant admin invites / suspends / removes a member | MODE_TENANT write, permission-guarded; a membership write for another tenant is rejected | 3 |
| 73 | Customer address book across stores | own book readable in any store; another customer's never; staff see only their store's `order_addresses` snapshots | 2/8 |
| 74 | Domain verification with an expired token | rejected; the domain stays pending | 7 |
| 75 | Second `is_primary` domain for a tenant | rejected by the partial unique index | 2 |
| 76 | Crash after business commit, before idempotency completion | retry gets 409 `idempotency_unresolved`; the operation is never re-executed | 4 |
| 77 | Expired claim reclaimed while its original request still runs | the original's fenced completion matches 0 rows; the new owner's result stands | 4 |
| 78 | Stale claimant attempts completion after another claimant acquired the key | overwrite rejected (claim-token fencing) | 4 |
| 79 | Concurrent deletion + publication of the same content hash (one store) | no live reference points at a deleted object; no orphaned object | 4 |
| 80 | Search: older queued write after newer state / after a tombstone | cannot overwrite or resurrect; the reconciler repairs stale documents | 5 |
| 81 | Platform settings applied inside a store request | the resolver supplies platform values; MODE_CENTRAL never entered | 4 |
| 82 | API route with an unclassified name (or an unnamed API route) | tenant resolution fails closed (404 envelope in production, throw in non-production) | 1 |
| 83 | Middleware lifecycle: audience decided after route match; context set before binding and credential application | proven behaviorally on real requests through the priority-sorted stack | 1 |
| 84 | Machine token matrix: valid / revoked / ordinary user token / header override | stored tenant selected; revoked or invalid fails closed; headers never override; an ordinary token is never treated as machine | 1 |
| 85 | Same root folder name in two tenants | both succeed — the partial index is per store | 2 |
| 86 | Two concurrent platform default-currency changes | at most one default survives (partial unique) | 2 |
| 87 | Two concurrent default-tax-class changes in one store | at most one default survives (partial unique) | 2 |
| 88 | Stale claimant reaches the commit marker after losing ownership | the helper affects 0 rows → throws → the business transaction rolls back | 4 |
| 89 | Reclaimed request triggers an external payment | the provider key = tenant + principal + key → same key, no duplicate charge | 4 |
| 90 | Domain verification under DNS rebinding (incl. IPv4-mapped IPv6, aliases) | the connection is pinned to a validated IP; a prohibited peer is never reached | 7 |
| 91 | Media per-key lease expires during a deletion | the stale owner fails closed before unlink; the still-referenced object survives | 4 |
| 92 | Product (any store-owned row) tenant reassignment | rejected — `tenant_id` is immutable | 2 |
| 93 | Missing or malformed platform setting | `PlatformSettingMissing` — never another store's value or a silent default | 4 |
| 94 | Provisioning path attempts to write any tenant id other than the new tenant | rejected by the runtime guard; the architecture test recognizes only the named path | 1 |
| 95 | Authenticated user provisions a store from the production-like central API host (`onboarding` audience) | succeeds with MODE_INACTIVE; no tenant resolution performed; no existing tenant required | 1 |
| 96 | Payment webhook on the central API host | Stage 2 resolves the tenant from `{tenant-slug}` before the controller runs; tenant headers ignored | 1 |
| 97 | Expired claim whose `committed_at` is already set | reclamation refused (`committed_at IS NULL` guard); `idempotency_unresolved`; the business operation is never re-executed | 4 |
| 98 | Registered central operation invoked from an unauthorized call site | rejected even though the operation exists in the registry | 1 |
| 99 | Media lease expires **between the executing transition and the unlink** | the stale worker's under-lock recheck fails and it never touches the filesystem; the newer generation's owner proceeds | 4 |
| 100 | Worker crashes mid-critical-section (lock held) or after the unlink, before finalization | the connection drop releases the lock; recovery finalizes (file gone) or re-verifies references under a fresh lease; no referenced file is lost | 4 |
| 101 | Direct SQL reassignment of a store-owned row's `tenant_id` | rejected by the database trigger, on PostgreSQL and SQLite alike | 2 |
| 102 | Bulk write whose request/DTO carries another tenant's `tenant_id` | the input is rejected before the write; the stamp comes only from `TenantWriteStamp` (context-derived) | 2 |
| 103 | Polymorphic collision: a non-user model with the same numeric id holding roles | cannot trigger the super-admin bypass (`model_type` + guard constrained) | 3 |
| 104 | Store admin lists memberships of another store | denied — member listing is scoped to the active tenant | 3 |
| 105 | Concurrent onboarding at the quota boundary (two PostgreSQL connections) | exactly the permitted number of tenants is created (user-row lock) | 1 |
| 106 | Deleting/disabling the last default tax class, a tenant's default currency, or the platform base currency without a replacement | rejected; at least one default remains | 2 |
| 107 | Store staff resolve an order code of their own store (with permission) | allowed when the resolved tenant equals the active tenant; other stores' codes still denied | 5 |
| 108 | Machine request against a suspended / soft-deleted tenant | denied before any tenant-scoped work | 1 |
| 110 | Middleware priority: Laravel defaults preserved; `ResolveTenant` precedes bindings, credentials, and tenant-aware throttling | asserted on the gathered stacks of representative admin/user/machine/webhook/throttled routes | 1 |
| 111 | Failure cleanup (`terminate()`) after the business transaction committed | the claim is **not** deleted — it becomes `idempotency_unresolved`; no clean retry is created | 4 |
| 112 | Lease reclamation attempted while an unlink transaction is open | blocks on the row lock until the unlink commits — no overlap between a newer owner and an in-flight unlink | 4 |
| 113 | Immutability triggers on the store-ownership `tenant_id` tables **that exist at each gate** (Phase 2: every Phase-2 table; Phase 4: + the two media coordination tables; Phase 8: the full inventory), checked against an explicit expected-table list that **excludes** installation-level tenant-*key* tables (machine tokens, spatie team tables) | migration test asserts presence + direct-SQL rejection on PostgreSQL and SQLite | 2/4/8 |
| 114 | Two concurrent idempotency reclaimers on the same expired claim | exactly one reclaims (serialized DELETE, unique-guarded INSERT); the other gets 409 | 4 |
| 115 | A different `CentralOperation` class returns an already-registered `id()` string | obtains nothing — the registry binds exact final classes, not strings | 1 |
| 116 | Deletion-intent lifecycle: duplicate sweeps, ambiguous unlink failure, publication against an executing intent | idempotent finalize; the intent survives for retry; publication resolves the intent first | 4 |
| 117 | DB connection terminated while a deletion worker is mid-critical-section but still alive | the stale worker's later filesystem call can only touch its **retired generation**; no live-referenced object is lost | 4 |
| 118 | Queued job whose payload carries `central_operation` | the listener sets no mode; central reads happen only inside `CentralOperations::run()` — a job entering MODE_CENTRAL directly fails | 1 |
| 119 | Competing owner-set mutations on two PostgreSQL connections (two owners deactivated concurrently) | serialized on the tenant row; the last active owner is never removed | 3 |
| 120 | Deterministic stale-worker race: A suspended after DB-session loss; B recovers, cancels the intent, and publishes a new generation; A resumes its rename | the new publication remains accessible; its exact object generation is intact; only the retired key moved | 4 |
| 121 | Trash objects addressed through the public media namespace, direct HTTP, or any disk URL mapping | not reachable — trash lives on a dedicated private disk (`trash://`) with no public web-server mapping at all; only the cleanup process can access it | 4 |
| 122 | A deletion intent targets a key, recovery cancels it, the terminal intent is pruned, and a later upload has identical content; the mirror case re-uploads against an eligible no-history object | the retired key is **never** reused — the new reference points at a different physical generation; the eligible object **is** reused (the optimization still works) | 4 |
| 123 | Concurrent attach-vs-retire on one generation, **genuinely independent PostgreSQL connections, both orderings**: A locks the generation row to attach a reference and pauses; B attempts retirement — then the reverse | B is verified to block on the generation row until A commits, then sees the live reference and does not retire; reversed, B retires first and A's locked read finds `retired` → A publishes a fresh generation; the final **database and physical object state** is asserted, not just the lock wait; no deadlock (generation row always locked first) | 4 |

---

## 11. Implementation-readiness assessment

Rev 4.1's eight second-review findings remain incorporated: (1) idempotency redesigned as concurrency
control with composite uniqueness and atomic reservation (§6.6); (2) every async dispatch origin
enumerated with no-inheritance and dual-model test requirements (§6.1); (3) layered integrity with
intra-module composite FKs, in-transaction validation, batch-before-sync, and pivot-aware auditing
(§4.3); (4) central mode behind `CentralOperations` with a registry, mandatory audit reason, and
exception-safe restore (§6.5); (5) the payment resolver contract covers every F25 config read-site
and the paypal-cache contradiction is fixed by tenant+version scoping (§6.3); (6) the queue
lifecycle works identically for sync and worker execution — verified against `SyncQueue` source,
with the rev-3 mid-request deactivation hazard fixed via snapshot/restore (F27, §6.1); (7) F2's
fail-open description corrected (IS NULL, not unscoped); (8) the idempotency unique constraint is
replaced, not extended (§6.6). The missing-requirements table (§9.7) and the final security gate
(§8) close the operational gaps.

Rev 4.2 adds what the inventory showed: the owner decisions (§0), the table classification (§4.4),
the unique-index dispositions (§4.5), how models pick up the filter (§2.1), the middleware order
(§3.1), and the subsystems that assumed one store (§6.7–§6.12) with their phases. Rev 4.3 closes
the external review of rev 4.2: the three-category execution boundary (§6.5), portable
NULL-collision uniqueness via partial indexes (§4.5, §5), crash-safe idempotency fencing (§6.6),
settled address semantics (§2.1, §4.4), the completed domain lifecycle (§4.1), the
platform-settings read path (§6.7), media same-key serialization (§6.4), search stale-write
guarantees (§6.8), the BIGINT/orphan-trade-off wording (§4.2–§4.3), the route-audience grammar
(§3.1), and the order-code lookup contract (§6.9). Rev 4.4 closes the second review: the
two-stage identification lifecycle with a priority-sorted middleware order (§3.1), machine-token
tenant storage and pre-binding resolution (§4.4, §9.7), the per-store root-folder index (§4.5),
the abort-on-lost-ownership idempotency marker and stable provider keys (§6.6), DNS-rebinding
pinning (§9.7), the default-currency/default-tax-class invariants (§4.5, §6.7.1), the media
database lease (§6.4), the provisioning write contract (§6.5), tenant-ownership immutability
(§6.8), and the resolver contract (§6.7). Rev 4.5 closes the third review: the tenant-neutral
`onboarding` audience (§3.1), webhook slug resolution (§3.1/§6.3), the `committed_at IS NULL`
reclamation guard and in-`handle()` completion (§6.6), the typed `CentralOperation` registry
(§6.5), the media lock table and deletion state machine (§4.4/§6.4), four-layer immutability
incl. the database trigger (§2/§6.8), priority-list default preservation with throttling order
(§3.1), and the polymorphic `model_type` constraint (§5), plus F1–F7. Rev 4.6 closes the fourth
review: the row-lock media fencing with the `media_deletion_intents` lifecycle contract
(§4.4/§6.4), per-engine immutability triggers with the pivot strategy (§6.8), the
every-deletion-path `committed_at IS NULL` guard and corrected reclamation wording (§6.6),
one canonical onboarding route (§4.1/§6.5/§9.7), per-query-site allowlists, the
`TenantWriteStamp` enforcement mechanism, and exact-class central-operation binding. Rev 4.7
closes the fifth review (of the condensed rev 4.6): rename-then-unlink media fencing with the
session-loss race bounded and documented (§6.4), the queue's payload can no longer grant central
mode — one enforceable `CentralOperations` entry point (§6.1), `media:outbox:sweep` reclassified
as central with its tables enumerated (§6.7.2), the 130/132/137 inventory reconciled (§4.4),
owner-set mutations serialized on the tenant row (§6.5), the provisioning context transition
specified (§6.5), the per-(tenant, driver) resolver cache identity (§6.3), and the pivot
threat-model boundary (§6.8). B3 was withdrawn by the reviewer after verifying the Laravel 13
`SyncQueue` source (F27). Rev 4.8 closes the sixth review of the full document: the
**generation-safe media object lifecycle** — immutable per-publication keys, retirement on
deletion history, intents naming their exact generation — eliminating (not bounding) the
stale-worker race (§6.4); `media:outbox:sweep` as **central discovery + tenant-scoped
execution** so no globally scheduled command writes store-owned rows (§6.7.2); phased trigger
acceptance with an explicit, key-column-excluding table list (row 113); the §4.4 heading
corrected to "no store-ownership scope"; and the trash root fenced out of public serving (§6.4,
row 121). Rev 4.9 closes the seventh review's clarifications: **durable retirement** in
`media_object_generations` (retirement survives intent pruning; cancellation never un-retires;
reuse eligibility reads the generation record, never intent history), the physical-creation vs
logical-reference wording split with six explicit invariants, the physical trash root
(`{disk}://trash/{tenant_id}/…`, sibling of the public tree), the sweep's stage-2 contract, and
row 122. Rev 4.10 closes the eighth review's single blocker: the **generation row as the common
serialization point** for reference attachment and retirement, with one-way transitions
(application guard + engine-branched trigger) and a fixed lock ordering (generation row before
reference rows), plus the dedicated private trash disk and row 123's two-connection
attach-vs-retire test in both orderings.

**Verdict: rev 4.10 is approved by the external review (9.7/10 — eighth round, final).** The
architecture is approved as written: tenant isolation, media object identity, deletion recovery,
stale-worker safety, concurrency serialization, and central cleanup are coherent end to end. The
one carried clarification — reference detachment and every reference-set mutation follow the
same generation-first lock ordering, with retirement-deciding checks held under the lock — is
folded into §6.4 as an implementation-contract note, not a new revision. **Approval of the plan
is not approval of implementation diffs or permission to commit:** Phase 1 may start on the
owner's go, with each phase reviewed against its changed-file list, static analysis, and test
results before the next is authorized, and the media lifecycle tests (rows 79, 91, 99–100, 112,
116–117, 120–123) are mandatory Phase 4 acceptance criteria. P1–P5 and the two classification
calls remain owner confirmations (§0), each required before the phase that implements it —
Phase 1 implements none of them.

### Appendix A — contingency only if A1 changes

Not applicable under the confirmed owner decision. If the owner later establishes that a database
with must-preserve data exists, stop before Phase 2 and redesign the migration sequence: create the
default tenant; add nullable `tenant_id` columns; backfill all existing rows to the default tenant;
then enforce NOT NULL and add indexes/tenant-scoped unique constraints. Do not execute this appendix
unless A1 is explicitly reversed and the migration plan is re-approved.

---

## Implementation Record

The owner authorized implementation of Phases 2–8 on 2026-11-25 and waived the between-phase
review gate. Proposals P1–P5 and the two classification calls are implemented as written unless
the owner says otherwise. Every phase is still verified — `composer analyse`, Pint, the SQLite
suite, and the PostgreSQL suite where it applies — before the next phase builds on it. This log
records what landed and where the build deviates from the plan text.

### Phase 1 — Tenancy core + Tenant module

Landed. Identification is live: `NormalizeTenantRequest` runs on every request, `ResolveTenant` is
first in the `api` group with the priority list extended rather than replaced, and it resets the
context to INACTIVE as its first act — under php-fpm the framework boots per request, and the
explicit reset keeps the invariant wherever a process survives (queue workers, the suite). Queue
payloads carry `{tenant_id, central_operation}`; the `JobProcessing`/`JobAttempted` listeners run
the job under the dispatching tenant on a snapshot stack. Central mode is reachable only through
`CentralOperations` — a registry keyed on the exact operation class with authorized callers, a
mandatory reason, a transaction that commits the operation together with its audit record, and an
architecture test that scans the application source for call sites that bypass it. The Tenant
module exposes the platform super-admin's store and domain CRUD (ten routes, created without
permission rows, as §4.1 requires), the tenant-neutral onboarding endpoint
(`POST /api/v1/onboarding/tenants`, the per-user quota serialized by a row lock), and machine
tokens minted for the issuing admin's store.

Deviations from the plan text:

1. **`TENANT_ID` is a final class (`Modules\Core\Schemas\Shared\TenantColumn`), not a shared
   schema trait.** PHP resolves trait constants only through the using class, so the scope and
   the model trait cannot reference a trait's constant.
2. **The default tenant is seeded by `TenantDatabaseSeeder`, not `CoreDatabaseSeeder`.** Core
   cannot reference the Tenant module; the owner membership is read through the Auth gateway.
3. **The built-in default tenant exists outside production, not in `testing` only (revised).** The
   first cut applied it in `testing` to an *empty* tenants table — a rule that took the loopback
   storefront down the moment a second store was created in a dev stack. The rule is now: outside
   production, the default resolves to a built-in snapshot whenever no real row holds its id or
   slug, and a real row always wins. Production must seed the default tenant and fails closed
   without it. The trade-off is deliberate and should be read as what it is: an environment that
   is not `production` and was never seeded serves the built-in default instead of revealing the
   missing seed. The production deploy must run `TenantDatabaseSeeder` — that belongs to Phase 8.
4. **A missing tenancy table reads as "no rows" outside production, and nothing else does.** The
   plan's gate is "the entire existing suite green with zero edits". The first full run broke it
   by exactly one test: route tests that never migrate a database — nothing before the controller
   read the database until identification began reading it on every request — answered 500 on
   "no such table: `tenant_domains`". `TenantGateway` reads a missing-table query error
   (SQLSTATE 42P01 / 42S02 / SQLite's `no such table`) as "no rows" outside production, classified
   from the exception itself — see the Phase 1 verification note for the PostgreSQL mechanics and
   the broken-schema boundary. A broken or half-installed schema (any other error) and everything
   in production are raised as they occurred. `UninstalledSchemaTest` pins it from both sides;
   the existing test is unchanged.
5. **Declared platform routes (landed in config).** The live route table confirms the
   third-segment grammar (296 admin, 63 user, 42 public, 2 webhook, 4 machine) but also holds
   routes without an audience segment: the unnamed `api/user` (signed-in-user lookup) and
   `api.notification.` (broadcasting auth). Undeclared, the fail-closed rule would 404 the
   signed-in-user lookup.
6. **The Octane pieces of this phase are withdrawn (rev 6, php-fpm revert).** The
    `ResetTenantContext` listener, its placement relative to the other `RequestReceived`
    listeners, and the isolation-gate tenant scenarios existed only for the persistent-worker
    runtime and were dropped in the rebase onto the php-fpm `main`; `ResolveTenant`'s per-request
    reset is the single owner of the invariant.
7. **The priority list is extended with `prependToPriorityList`, never replaced.** Laravel's
   default ordering stays intact; `ResolveTenant` is inserted before its first entry.
8. **`AuthorizeTenantAccess` lands with Phase 3, not Phase 1.** Its admin rule depends on
   memberships, which gate nothing before Phase 3.
9. **Payment webhooks resolve by host until Phase 4, and the route parameter is `{tenantSlug}`.**
   A route parameter with a hyphen registers but never matches, so the plan's `{tenant-slug}`
   cannot be read; Stage 2 reads `{tenantSlug}`. The route itself changes in Phase 4.
10. **K3 is closed in Phase 1, not Phase 2.** `MachineTokenRequest` validated the token's user
    against `users` — the profile table — so no machine token could be minted at all, and Phase 1
    exercises its resolution through that mint. It now validates against `auth_users`. K4, the
    ownership filter, stays in Phase 2.
11. **The `abilities` middleware alias is registered (a pre-existing defect, found here).** The
    machine routes declare `abilities:<ability>`, but Sanctum 4 ships `CheckAbilities` without
    registering that alias, so those routes could not have run. `WebhooksServiceProvider` registers
    it because Phase 1's tests need them.
12. **Test tooling.** The Tenant helpers are declared on Pest's proxies in `phpstan/test-stub.php`,
    as every module's are. The baseline gains exactly one entry — the Tenant `HelperTrait`'s
    "unused trait" false positive, which each of the other modules also carries. No other baseline
    line was added or changed.
13. **The test process gets its own memory limit (512M, in `scripts/test.sh` and CI's two pest
    steps — landed on `main` with the revert as `c58822f6`).** The suite is one long-lived PHP process that retains about 100 KB per test. Measured
    with one probe on the same 366 tests: `main` went 90.2 MB → 113.8 MB (test 100 → test 340),
    this branch 90.5 MB → 114.7 MB — the same, so the retention predates tenancy. It is reachable
    from no application static, and an in-process request loop through the middleware is flat at
    0 KB per request on every audience. At about 1,600 tests the suite outgrows the image's 256M
    in its last tests (it died writing Pest's result cache, exit 255), and `main` was already
    within a few MB of that. The limit is set where the suite is invoked, so the web runtime
    keeps 256M.

Tooling notes: Larastan does not type-check `??=` assignments to typed properties (verified with
a minimal probe). A `TypeError` in `TenantContext::tenant()` slipped through an exit-0 run; the
coalesce-assign is avoided in this work and the path is covered by a test. Pest's `toThrow()`
closure form asserts only the exception's class — it takes the closure's parameter type as the
expected class and discards what the closure returns — so seven assertions that read as "refuses
with code X" asserted nothing but the class. They go through `assertThrowsCode()` now, which
checks the code, and the HTTP status where one matters.

Observed on the way, not fixed: a guest request that does not ask for JSON gets 500 from an
authenticated API route (`Route [login] not defined`) — Laravel's default `Authenticate`
redirect assumes a `login` route this API-only app does not define. JSON requests get 401.
Pre-existing and unrelated to tenancy; recorded for Phase 3.

Phase 1 verification (closed out 2026-11-27, after the rev-6 rebase):

- **The two parked PostgreSQL failures in `UninstalledSchemaTest` were a real gateway bug, not a
  test quirk.** `TenantGateway::read()` classified a missing table by querying
  `Schema::hasTable()` in the catch block — but on PostgreSQL the failed statement has already
  put the transaction into the aborted state, so that follow-up query itself failed with 25P02
  (SQLite has no such state, which is why the engine difference never showed in isolation). The
  fix has two parts: the classification now reads the exception itself — SQLSTATE 42P01
  (PostgreSQL undefined_table), 42S02 (MySQL), or SQLite's `no such table` — with no follow-up
  query; and each read runs inside its own savepoint, so a failed read is undone with
  `ROLLBACK TO SAVEPOINT` and any enclosing transaction (a caller's, or the test harness's)
  stays usable — SQLite and MySQL treat the savepoint as a no-op. The one behaviour change that
  follows: "which tenancy table is missing" is no longer distinguishable without a follow-up
  query, so outside production a missing table anywhere in the tenancy schema is forgiven, and a
  **broken** schema (any other error — e.g. a missing column) is still raised. The broken-schema
  test is PostgreSQL-only: SQLite's double-quoted-string fallback turns a quoted unknown column
  into a string literal, matching zero rows instead of failing, so the scenario cannot be
  expressed there.
- **Deviation 4's wording is superseded by the above** (absent-`tenants`-table rule →
  missing-table-error rule); the `CentralOperations::recordFailedAttempt()` savepoint (its own
  entry above) is confirmed on PostgreSQL — an operation whose audit write fails rolls back
  without leaving the connection aborted.
- **Final numbers** (2026-11-27, on the rebased php-fpm runtime): `composer analyse` clean
  (exit 0); Pint clean; the Tenant module suite passes both planes — SQLite 66 passed/2 skipped
  (the two PostgreSQL-gated tests), PostgreSQL 68 passed/0 skipped; the full suite is green on
  both legs — SQLite **1603 passed / 7 skipped** (the PG-gated validations), PostgreSQL
  **1610 passed / 0 failed** (~20 min, every gate runs there). Phase 1 is done; Phase 2 landed on 2026-11-27 (record below);
  Phases 3–8 are not started.

---

### Phase 2 — Data scoping (backend bulk)

Landed. Step 0 was closed first: the dev database was recreated from the working-tree migrations and the authoritative columns/indexes were dumped before any Phase 2 code edit (see the Step 0 reconciliation above; the final snapshot is retained in the verification record). The bulk pass then added tenant ownership to all 82 store-owned tables, the nine tenant-less pivots, and the two platform-tier nullable-owner tables; promoted every store-owned Eloquent read to `TenantScopedTrait`/`TenantQueryScope`, every write to the context-derived `TenantWriteStamp`, every `unique:`/`exists:` rule to the scoped rules, and every raw query site to the exact per-site allowlist. Intra-module composite foreign keys replaced their single-column duplicates, immutability triggers cover every `tenant_id` table that exists at this gate, and `tenancy:audit` is a registered Core `CentralOperations` operation with a `--reason` and a relation map that includes the nine pivots.

**Step 0 / dump result (authoritative final snapshot, 2026-11-27).** `pg_dump`-style introspection of the migrated dev database produced **136 base tables**, **87 `tenant_id` columns** (82 store-owned NOT NULL, 3 nullable platform-tier, 4 Tenant metadata), **82 immutability triggers**, **76 foreign keys**, **111 non-PK unique indexes** (100 entry-dump dispositions plus 11 supporting `(tenant_id, id)` parent keys for the composite FKs) and **6 partial unique indexes**. The canonical inventory is 82 store-owned tables + 9 tenant-less pivots + 2 platform-tier nullable-owner tables; installation-level tenant-*key* tables (machine tokens, the Phase 3 spatie team keys) are excluded. No §4.4/§4.5 difference remains unresolved.

Deviations from the plan text:

1. **The Step 0 batch checklist's `fillable`/factory/`seedTenant()` edits were not applied.** §4.3 says `tenant_id` is never fillable and is stamped by `TenantScopedTrait::creating()` from `TenantContext`; adding it to `$fillable`, factories or a per-module helper would open the exact input channel row 102 closes. Multi-tenant tests use `runForTenant()` (and `productionLikeTenancy()`) with the existing helpers; the context is the single write authority.
2. **`TenantColumn` stays a final class (as in Phase 1), and `PrimaryKeyColumn` was added.** PHPStan cannot read `PkAndTimestampsTrait::ID` through a using class; the new explicit column holder avoids a baseline entry.
3. **Currency identity snapshots were added in Phase 2, not left for a later phase.** `order_invoices.exchange_rate`, `order_credit_notes.exchange_rate`, `payment_payments.currency_code`/`exchange_rate` and `payment_refunds.currency_code`/`exchange_rate` are present in the in-place migrations. Composite index names were shortened where the generated name would exceed PostgreSQL's 63-byte identifier limit.
4. **Composite FKs are strictly intra-module.** Cross-module references stay gateway-validated ids (§4.4); the composite keys replace single-column duplicates rather than adding alongside them. TenantQueryScope fails closed outside `MODE_TENANT` (including CENTRAL), so a raw query cannot silently read all stores.
5. **`tenancy:audit` is a Core operation, and the allowlists are exact.** `tenancy:audit` runs through `CentralOperations` with a mandatory `--reason`; the audit event is written after the operation commits in CLI context. Query-site and validation-rule allowlists are keyed by file/method/table/occurrence and regenerated from the scanner; the registry test now checks each site's justification against how the scanner actually guards it, and every centrally justified entry names its reason (audit traversal, scoped rule, transfer remap, parent-scoped pivot, declared console mode, bootstrap webhook) rather than carrying a generic label. No new `phpstan-baseline.neon` entries were added; the stale Tenant `HelperTrait` ignore was removed.
6. **Row 31's cross-tenant collision is closed at the Phase 2 schema level, not with the full §6.6 protocol.** The original installation-wide `KEY` unique on `core_idempotency_keys` is replaced in place with `UNIQUE(tenant_id, key)`; a two-tenant schema test proves that A and B may use the same key while one store may not use it twice. The plan assigns the rest of the redesign (`principal_key`, `status`, atomic claim/fencing, 409-in-progress) to Phase 4, so this Phase 2 change closes the isolation defect without half-implementing the reservation protocol.
7. **Default lifecycle guards are enforced where Phase 2 owns the mutation.** Deleting or directly demoting the incumbent default tax class is rejected, with a two-tenant proof that each store keeps its own default and a replacement promotion is transactional. Unsetting the store's `currency_id` setting is rejected by request validation, and the base currency cannot be disabled or have its rate changed via the currency update action. The remaining conflation — a store's display-currency setting still mirrors into the platform-wide `core_currencies.is_default` through `UpdateSettingAction`/`CoreGateway::markCurrencyDefault()` — is carried forward: separating per-store default selection from the platform base flag requires the §6.7 resolver and its consumers, which the plan assigns to Phase 4.
8. **§6.7.2 console command run modes are Phase 5 work.** The Phase 2 registry declares only `tenancy:audit` (central, registered `CentralOperations`, mandatory `--reason`). Centrally justified query sites name their concrete reason; the full central/platform/tenant-fan-out/bootstrap/discovery+fan-out declaration table and its architecture test are not pulled forward, per the plan's Phase 5 assignment.
9. **Row 19 and row 73 proofs were added without pulling Phase 5 transfer work forward.** A two-tenant catalog export→import test proves A's artifact never writes into B and vice versa, and a two-tenant test proves the customer address book stays global/user-owned while staff-facing `order_addresses` snapshots stay scoped to their store. The remaining transfer infrastructure (global `transfer_remaps` source unique, `transfer:import:{module}` lock name, cross-store remap tier) stays in Phase 5.

Verification (all runs used Docker Compose; no host PHP/Node):

- `composer analyse` — clean (exit 0) after clearing the PHPStan result cache.
- Pint — `vendor/bin/pint --test` clean on all 273 changed PHP files with a fresh cache file (exit 0).
- SQLite full suite (`docker compose exec -T app nice -n 10 sh scripts/test.sh`) — **7 skipped, 1640 passed (6393 assertions)**, 1049.52s, exit 0.
- PostgreSQL full suite (`docker compose exec -T -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_DATABASE=baxela_backend ... app nice -n 10 sh scripts/test.sh`) — **1647 passed (6411 assertions)**, 1991.00s, exit 0.

Phase 2 review (2026-11-27, post-implementation audit):

- **Found and fixed: three early-stamped tables kept their installation-wide unique keys** — the
  exact contradiction deviation 6 fixed for `core_idempotency_keys`, missed on the other three
  R/T-class tables that were stamped early. `order_document_sequences` kept `UNIQUE(type, year)`:
  the first store to allocate a document number that year permanently blocked every other store's
  first allocation (the scoped re-query after the unique-violation catch finds nothing → 500).
  `payment_driver_credentials` kept `UNIQUE(driver, key)`: the second store to configure the same
  driver key hit the unique on `updateOrCreate` — A2 says per-tenant credentials from day one.
  `transfer_remaps` kept the four-column `transfer_remaps_source_unique`: one store's import of an
  artifact blocked every other store's import of the same source key, breaking the documented
  convergence semantics. All three widened in place to the plan's §4.5 dispositions
  (`UNIQUE(tenant_id, type, year)`, `UNIQUE(tenant_id, driver, key)`, leading-`tenant_id`
  remap key); `Phase2SchemaInvariantsTest` gains the two-store proofs for each.
- **Observation, no action**: a full-repo Pint run reports 52 style failures, all in files
  Phase 2 never touched (pre-existing on `main`; the changed-file run above stays clean).
- Re-verification after the fixes: `composer analyse` clean; Pint clean on the touched files;
  Order (110 passed) and Payment (352 passed) module suites green; registry + Core tenancy tests
  240 passed; SQLite full suite **7 skipped, 1641 passed (6396 assertions)**; PostgreSQL full
  suite **1648 passed (6414 assertions)**, exit 0. (Running the PostgreSQL plane under pest
  `--parallel` shares one database across the four processes and produces cross-suite
  interference failures — 8 spurious fails that pass serially; the plane is run serially, as
  the original verification was.)

<details>
<summary>Changed files (274: 273 PHP files plus this plan document)</summary>

```text
apps/backend/Modules/Audit/app/Models/AuditLog.php
apps/backend/Modules/Audit/database/migrations/2026_10_12_000001_create_audit_logs_table.php
apps/backend/Modules/Cart/app/Models/CartItem.php
apps/backend/Modules/Cart/app/Models/Cart.php
apps/backend/Modules/Cart/database/migrations/2026_03_11_022631_create_carts_table.php
apps/backend/Modules/Cart/database/migrations/2026_03_11_023029_create_cart_items_table.php
apps/backend/Modules/Catalog/app/Actions/Admin/DataTransfer/ExportCatalogDataAction.php
apps/backend/Modules/Catalog/app/Actions/Admin/DataTransfer/ImportCatalogDataAction.php
apps/backend/Modules/Catalog/app/Actions/Admin/Featured/UpdateFeaturedAction.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Attribute/AttributeRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/AttributeTemplate/AttributeTemplateRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Brand/BrandRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Category/CategoryRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Featured/UpdateFeaturedRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Option/OptionRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/OptionValue/OptionValueRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/ProductComment/ProductCommentRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/Admin/Product/ProductRequest.php
apps/backend/Modules/Catalog/app/Http/Requests/User/ProductComment/ProductCommentRequest.php
apps/backend/Modules/Catalog/app/Models/AttributeGroup.php
apps/backend/Modules/Catalog/app/Models/AttributeGroupTranslation.php
apps/backend/Modules/Catalog/app/Models/Attribute.php
apps/backend/Modules/Catalog/app/Models/AttributeTemplate.php
apps/backend/Modules/Catalog/app/Models/AttributeTranslation.php
apps/backend/Modules/Catalog/app/Models/AttributeValue.php
apps/backend/Modules/Catalog/app/Models/AttributeValueTranslation.php
apps/backend/Modules/Catalog/app/Models/Brand.php
apps/backend/Modules/Catalog/app/Models/BrandTranslation.php
apps/backend/Modules/Catalog/app/Models/CatalogImport.php
apps/backend/Modules/Catalog/app/Models/Category.php
apps/backend/Modules/Catalog/app/Models/CategoryTranslation.php
apps/backend/Modules/Catalog/app/Models/FeaturedItem.php
apps/backend/Modules/Catalog/app/Models/Image.php
apps/backend/Modules/Catalog/app/Models/Option.php
apps/backend/Modules/Catalog/app/Models/OptionTranslation.php
apps/backend/Modules/Catalog/app/Models/OptionValue.php
apps/backend/Modules/Catalog/app/Models/OptionValueTranslation.php
apps/backend/Modules/Catalog/app/Models/ProductAttributeValue.php
apps/backend/Modules/Catalog/app/Models/ProductComment.php
apps/backend/Modules/Catalog/app/Models/ProductCommentReaction.php
apps/backend/Modules/Catalog/app/Models/Product.php
apps/backend/Modules/Catalog/app/Models/ProductSeoTranslation.php
apps/backend/Modules/Catalog/app/Models/ProductShipping.php
apps/backend/Modules/Catalog/app/Models/ProductTranslation.php
apps/backend/Modules/Catalog/app/Models/Variant.php
apps/backend/Modules/Catalog/app/Support/ProductImport/ProductImportOptionResolver.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_184900_create_brands_table.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_185021_create_products_table.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_185247_create_variants_table.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_185328_create_options_table.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_185408_create_option_values_table.php
apps/backend/Modules/Catalog/database/migrations/2026_02_12_185534_create_categories_table.php
apps/backend/Modules/Catalog/database/migrations/2026_03_27_075047_create_images_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_23_000001_create_attribute_groups_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_23_000002_create_attributes_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_23_000003_create_attribute_values_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_23_000005_create_product_attribute_values_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_23_000006_create_attribute_templates_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_25_000001_create_product_seo_translations_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_26_000001_create_product_shipping_table.php
apps/backend/Modules/Catalog/database/migrations/2026_08_30_000001_create_product_comments_table.php
apps/backend/Modules/Catalog/database/migrations/2026_09_29_000001_create_catalog_imports_table.php
apps/backend/Modules/Catalog/database/migrations/2026_10_02_000001_create_featured_items_table.php
apps/backend/Modules/Catalog/database/migrations/2026_10_18_000001_create_product_comment_reactions_table.php
apps/backend/Modules/Catalog/database/seeders/BrandSeeder.php
apps/backend/Modules/Catalog/database/seeders/CategorySeeder.php
apps/backend/Modules/Catalog/database/seeders/ProductSeeder.php
apps/backend/Modules/Catalog/tests/Feature/Admin/CatalogDataTransferTest.php
apps/backend/Modules/Catalog/tests/Feature/Tenancy/ProductTenantIsolationTest.php
apps/backend/Modules/Contact/app/Models/ContactMessage.php
apps/backend/Modules/Contact/app/Models/NewsletterSubscriber.php
apps/backend/Modules/Contact/database/migrations/2026_09_09_000100_create_contact_messages_table.php
apps/backend/Modules/Contact/database/migrations/2026_09_30_000100_create_newsletter_subscribers_table.php
apps/backend/Modules/Content/app/Actions/Admin/Featured/UpdateFeaturedAction.php
apps/backend/Modules/Content/app/Http/Requests/Admin/Featured/UpdateFeaturedRequest.php
apps/backend/Modules/Content/app/Http/Requests/Admin/Page/PageRequest.php
apps/backend/Modules/Content/app/Http/Requests/Admin/PostCategory/PostCategoryRequest.php
apps/backend/Modules/Content/app/Http/Requests/Admin/PostComment/PostCommentRequest.php
apps/backend/Modules/Content/app/Http/Requests/Admin/Post/PostRequest.php
apps/backend/Modules/Content/app/Http/Requests/User/PostComment/PostCommentRequest.php
apps/backend/Modules/Content/app/Models/FeaturedItem.php
apps/backend/Modules/Content/app/Models/Page.php
apps/backend/Modules/Content/app/Models/PageTranslation.php
apps/backend/Modules/Content/app/Models/PostCategory.php
apps/backend/Modules/Content/app/Models/PostCategoryTranslation.php
apps/backend/Modules/Content/app/Models/PostComment.php
apps/backend/Modules/Content/app/Models/Post.php
apps/backend/Modules/Content/app/Models/PostSeoTranslation.php
apps/backend/Modules/Content/app/Models/PostTranslation.php
apps/backend/Modules/Content/app/Transfer/ContentTransfer.php
apps/backend/Modules/Content/database/migrations/2026_03_13_202913_create_pages_table.php
apps/backend/Modules/Content/database/migrations/2026_10_03_000001_create_posts_table.php
apps/backend/Modules/Content/database/migrations/2026_10_03_000002_create_post_categories_table.php
apps/backend/Modules/Content/database/migrations/2026_10_03_000003_create_post_comments_table.php
apps/backend/Modules/Content/database/migrations/2026_10_09_000002_create_post_seo_translations_table.php
apps/backend/Modules/Content/database/migrations/2026_10_10_000002_create_featured_items_table.php
apps/backend/Modules/Core/app/Actions/Tenancy/AuditTenancyAction.php
apps/backend/Modules/Core/app/Console/Commands/TenancyAuditCommand.php
apps/backend/Modules/Core/app/Contracts/Gateways/Tenant/TenantGatewayInterface.php
apps/backend/Modules/Core/app/Models/IdempotencyKey.php
apps/backend/Modules/Core/app/Models/Scopes/TenantScope.php
apps/backend/Modules/Core/app/Models/Traits/TenantScopedTrait.php
apps/backend/Modules/Core/app/Models/TransferImport.php
apps/backend/Modules/Core/app/Models/TransferRemap.php
apps/backend/Modules/Core/app/Operations/AuditTenancyOperation.php
apps/backend/Modules/Core/app/Providers/CoreServiceProvider.php
apps/backend/Modules/Core/app/Rules/LanguageUniquePair.php
apps/backend/Modules/Core/app/Rules/LowercaseUniquePair.php
apps/backend/Modules/Core/app/Rules/ScopedExists.php
apps/backend/Modules/Core/app/Rules/ScopedUnique.php
apps/backend/Modules/Core/app/Rules/UniquePair.php
apps/backend/Modules/Core/app/Schemas/Shared/PrimaryKeyColumn.php
apps/backend/Modules/Core/app/Support/Tenancy/AssertsTenantOwnership.php
apps/backend/Modules/Core/app/Support/Tenancy/CentralOperations.php
apps/backend/Modules/Core/app/Support/Tenancy/TenancyAudit.php
apps/backend/Modules/Core/app/Support/Tenancy/TenantQueryScope.php
apps/backend/Modules/Core/app/Support/Tenancy/TenantTableInventory.php
apps/backend/Modules/Core/app/Support/Tenancy/TenantWriteStamp.php
apps/backend/Modules/Core/config/config.php
apps/backend/Modules/Core/database/factories/CurrencyFactory.php
apps/backend/Modules/Core/database/migrations/2026_03_25_092852_create_idempotency_keys_table.php
apps/backend/Modules/Core/database/migrations/2026_05_27_141758_create_currencies_table.php
apps/backend/Modules/Core/database/migrations/2026_11_07_000001_create_transfer_imports_table.php
apps/backend/Modules/Core/database/migrations/2026_11_20_000002_create_transfer_remaps_table.php
apps/backend/Modules/Core/database/migrations/2026_11_27_000001_create_tenant_id_immutability_triggers.php
apps/backend/Modules/Core/tests/Feature/CollationPortabilityTest.php
apps/backend/Modules/Core/tests/Feature/FullInstallRoundTripTest.php
apps/backend/Modules/Core/tests/Feature/Phase2SchemaInvariantsTest.php
apps/backend/Modules/Core/tests/Feature/PostgresValidationTest.php
apps/backend/Modules/Core/tests/Feature/TenancyAuditCommandTest.php
apps/backend/Modules/Core/tests/Feature/TenantImmutabilityTriggersTest.php
apps/backend/Modules/Core/tests/Feature/TenantTableInventoryTest.php
apps/backend/Modules/Core/tests/Feature/TenantValidationRulesTest.php
apps/backend/Modules/Core/tests/Feature/TenantWriteStampTest.php
apps/backend/Modules/Core/tests/Feature/TransferImportSessionTest.php
apps/backend/Modules/Core/tests/Fixtures/Erd/Shop/app/Models/ErdDetail.php
apps/backend/Modules/Core/tests/Fixtures/Erd/Shop/app/Models/ErdNote.php
apps/backend/Modules/Core/tests/Fixtures/Erd/Shop/app/Models/ErdPost.php
apps/backend/Modules/Core/tests/Fixtures/Erd/Shop/app/Models/ErdSummary.php
apps/backend/Modules/Core/tests/Fixtures/Erd/Shop/app/Models/ErdTag.php
apps/backend/Modules/Discount/app/Actions/Admin/Promotion/CreatePromotionAction.php
apps/backend/Modules/Discount/app/Actions/Admin/Promotion/UpdatePromotionAction.php
apps/backend/Modules/Discount/app/Http/Requests/Admin/Coupon/CouponRequest.php
apps/backend/Modules/Discount/app/Models/Coupon.php
apps/backend/Modules/Discount/app/Models/Promotion.php
apps/backend/Modules/Discount/app/Models/Redemption.php
apps/backend/Modules/Discount/app/Transfer/DiscountTransfer.php
apps/backend/Modules/Discount/database/migrations/2026_09_24_000001_create_discount_coupons_table.php
apps/backend/Modules/Discount/database/migrations/2026_09_24_000002_create_discount_redemptions_table.php
apps/backend/Modules/Discount/database/migrations/2026_10_09_000001_create_discount_promotions_table.php
apps/backend/Modules/Discount/tests/Feature/DiscountRedemptionImportTest.php
apps/backend/Modules/Inventory/app/Models/InventoryStock.php
apps/backend/Modules/Inventory/database/migrations/2026_03_13_225319_create_inventory_stocks_table.php
apps/backend/Modules/Media/app/Actions/Admin/Media/CreateMediaAction.php
apps/backend/Modules/Media/app/Console/Commands/SweepMediaStagingCommand.php
apps/backend/Modules/Media/app/Http/Requests/Admin/Folder/FolderRequest.php
apps/backend/Modules/Media/app/Http/Requests/Admin/Media/MediaRequest.php
apps/backend/Modules/Media/app/Models/Folder.php
apps/backend/Modules/Media/app/Models/Media.php
apps/backend/Modules/Media/app/Support/MediaPublicationProcessor.php
apps/backend/Modules/Media/database/migrations/2026_07_09_215139_create_folders_table.php
apps/backend/Modules/Media/database/migrations/2026_07_10_215150_create_media_table.php
apps/backend/Modules/Media/database/migrations/2026_11_22_000002_create_media_outbox_table.php
apps/backend/Modules/Menu/app/Http/Requests/Admin/MenuLink/MenuLinkRequest.php
apps/backend/Modules/Menu/app/Http/Requests/Admin/Menu/MenuRequest.php
apps/backend/Modules/Menu/app/Models/MenuLink.php
apps/backend/Modules/Menu/app/Models/MenuLinkTranslation.php
apps/backend/Modules/Menu/app/Models/Menu.php
apps/backend/Modules/Menu/app/Models/MenuTranslation.php
apps/backend/Modules/Menu/app/Transfer/MenuTransfer.php
apps/backend/Modules/Menu/database/migrations/2026_09_09_000001_create_menus_table.php
apps/backend/Modules/Menu/database/migrations/2026_09_09_000002_create_menu_translations_table.php
apps/backend/Modules/Menu/database/migrations/2026_09_09_000003_create_menu_links_table.php
apps/backend/Modules/Menu/database/migrations/2026_09_09_000004_create_menu_link_translations_table.php
apps/backend/Modules/Notification/app/Models/Notification.php
apps/backend/Modules/Notification/database/migrations/2026_04_01_131239_create_notifications_table.php
apps/backend/Modules/Order/app/Actions/Admin/CreditNote/IssueCreditNoteAction.php
apps/backend/Modules/Order/app/Actions/Admin/Invoice/IssueInvoiceAction.php
apps/backend/Modules/Order/app/Gateways/DTOs/GetOrderOutput.php
apps/backend/Modules/Order/app/Models/DocumentSequence.php
apps/backend/Modules/Order/app/Models/OrderAddress.php
apps/backend/Modules/Order/app/Models/OrderCreditNote.php
apps/backend/Modules/Order/app/Models/OrderInvoiceItem.php
apps/backend/Modules/Order/app/Models/OrderInvoice.php
apps/backend/Modules/Order/app/Models/OrderItem.php
apps/backend/Modules/Order/app/Models/Order.php
apps/backend/Modules/Order/app/Models/OrderReturn.php
apps/backend/Modules/Order/app/Schemas/OrderCreditNote/OrderCreditNoteSchema.php
apps/backend/Modules/Order/app/Schemas/OrderInvoice/OrderInvoiceSchema.php
apps/backend/Modules/Order/app/Transfer/OrderTransfer.php
apps/backend/Modules/Order/app/Transformers/Admin/CreditNote/OrderCreditNoteResource.php
apps/backend/Modules/Order/app/Transformers/Admin/Invoice/OrderInvoiceResource.php
apps/backend/Modules/Order/database/migrations/2026_03_11_172406_create_orders_table.php
apps/backend/Modules/Order/database/migrations/2026_03_11_172413_create_order_items_table.php
apps/backend/Modules/Order/database/migrations/2026_03_12_011204_create_order_addresses_table.php
apps/backend/Modules/Order/database/migrations/2026_10_21_000002_create_order_returns_table.php
apps/backend/Modules/Order/database/migrations/2026_11_07_000001_create_order_invoices_table.php
apps/backend/Modules/Order/database/migrations/2026_11_07_000002_create_order_invoice_items_table.php
apps/backend/Modules/Order/database/migrations/2026_11_07_000003_create_order_credit_notes_table.php
apps/backend/Modules/Order/database/migrations/2026_11_07_000004_create_order_document_sequences_table.php
apps/backend/Modules/Order/tests/Feature/Admin/InvoiceTest.php
apps/backend/Modules/Order/tests/Feature/OrderAddressTenantScopeTest.php
apps/backend/Modules/Order/tests/Feature/OrderTransferImportTest.php
apps/backend/Modules/Payment/app/Actions/Admin/PaymentRefund/CreateRefundAction.php
apps/backend/Modules/Payment/app/Actions/User/Payment/CreatePaymentAction.php
apps/backend/Modules/Payment/app/Models/PaymentDriverCredential.php
apps/backend/Modules/Payment/app/Models/PaymentMethod.php
apps/backend/Modules/Payment/app/Models/Payment.php
apps/backend/Modules/Payment/app/Models/PaymentRefund.php
apps/backend/Modules/Payment/app/Schemas/Payment/PaymentSchema.php
apps/backend/Modules/Payment/app/Schemas/PaymentRefund/PaymentRefundSchema.php
apps/backend/Modules/Payment/app/Transfer/PaymentTransfer.php
apps/backend/Modules/Payment/app/Transformers/Admin/Payment/PaymentResource.php
apps/backend/Modules/Payment/app/Transformers/Admin/PaymentRefund/PaymentRefundResource.php
apps/backend/Modules/Payment/database/migrations/2026_03_12_205120_create_payments_table.php
apps/backend/Modules/Payment/database/migrations/2026_09_24_000000_create_payment_methods_table.php
apps/backend/Modules/Payment/database/migrations/2026_10_21_000001_create_payment_refunds_table.php
apps/backend/Modules/Payment/database/migrations/2026_11_14_100001_create_payment_driver_credentials_table.php
apps/backend/Modules/Payment/tests/Feature/Admin/DriverCredentialsTest.php
apps/backend/Modules/Payment/tests/Feature/Admin/Payment/UpdatePaymentTest.php
apps/backend/Modules/Payment/tests/Feature/Admin/RefundTest.php
apps/backend/Modules/Payment/tests/Feature/PaymentTransferImportTest.php
apps/backend/Modules/Payment/tests/Feature/Webhook/RefundOutcomeTest.php
apps/backend/Modules/Setting/app/Models/Setting.php
apps/backend/Modules/Setting/app/Models/Translation.php
apps/backend/Modules/Setting/database/migrations/2026_03_12_214600_create_settings_table.php
apps/backend/Modules/Setting/tests/Feature/SettingTest.php
apps/backend/Modules/Shipping/app/Http/Requests/Admin/Method/MethodRequest.php
apps/backend/Modules/Shipping/app/Http/Requests/Admin/Rate/RateRequest.php
apps/backend/Modules/Shipping/app/Models/Method.php
apps/backend/Modules/Shipping/app/Models/MethodTranslation.php
apps/backend/Modules/Shipping/app/Models/Rate.php
apps/backend/Modules/Shipping/app/Models/Shipment.php
apps/backend/Modules/Shipping/app/Models/Zone.php
apps/backend/Modules/Shipping/app/Transfer/ShippingTransfer.php
apps/backend/Modules/Shipping/database/migrations/2026_08_30_000001_create_shipping_methods_table.php
apps/backend/Modules/Shipping/database/migrations/2026_08_30_000002_create_shipping_method_translations_table.php
apps/backend/Modules/Shipping/database/migrations/2026_08_30_000003_create_shipping_zones_table.php
apps/backend/Modules/Shipping/database/migrations/2026_08_30_000005_create_shipping_rates_table.php
apps/backend/Modules/Shipping/database/migrations/2026_08_30_000006_create_shipping_shipments_table.php
apps/backend/Modules/Support/app/Models/TicketMessage.php
apps/backend/Modules/Support/app/Models/Ticket.php
apps/backend/Modules/Support/app/Transfer/SupportTransfer.php
apps/backend/Modules/Support/database/migrations/2026_10_04_000100_create_support_tickets_table.php
apps/backend/Modules/Support/database/migrations/2026_10_04_000200_create_support_ticket_messages_table.php
apps/backend/Modules/Tax/app/Actions/Admin/TaxClass/CreateTaxClassAction.php
apps/backend/Modules/Tax/app/Actions/Admin/TaxClass/UpdateTaxClassAction.php
apps/backend/Modules/Tax/app/Http/Requests/Admin/TaxRate/TaxRateRequest.php
apps/backend/Modules/Tax/app/Models/TaxClass.php
apps/backend/Modules/Tax/app/Models/TaxRate.php
apps/backend/Modules/Tax/app/Transfer/TaxTransfer.php
apps/backend/Modules/Tax/database/migrations/2026_10_20_000001_create_tax_classes_table.php
apps/backend/Modules/Tax/database/migrations/2026_10_20_000002_create_tax_rates_table.php
apps/backend/Modules/Tax/tests/Feature/Admin/TaxClassTest.php
apps/backend/Modules/Tenant/app/Gateways/TenantGateway.php
apps/backend/Modules/Tenant/tests/Feature/Http/MachineTokenTenantDataTest.php
apps/backend/Modules/Tenant/tests/Feature/Registry/TenancyAuditRelationMapArchitectureTest.php
apps/backend/Modules/Tenant/tests/Feature/Registry/TenantCommandModeArchitectureTest.php
apps/backend/Modules/Tenant/tests/Feature/Registry/TenantQuerySiteArchitectureTest.php
apps/backend/Modules/Tenant/tests/Feature/Registry/TenantValidationRuleArchitectureTest.php
apps/backend/Modules/Tenant/tests/Feature/TenantContextTest.php
apps/backend/Modules/Tenant/tests/Fixtures/TenantQueryScanner.php
apps/backend/Modules/User/app/Models/WishlistItem.php
apps/backend/Modules/User/database/migrations/2026_09_10_000001_create_wishlist_items_table.php
apps/backend/Modules/Webhooks/app/Actions/Webhooks/ProcessOutboxEventAction.php
apps/backend/Modules/Webhooks/app/Console/Commands/PruneWebhooksCommand.php
apps/backend/Modules/Webhooks/app/Models/WebhookDelivery.php
apps/backend/Modules/Webhooks/app/Models/WebhookEndpoint.php
apps/backend/Modules/Webhooks/app/Models/WebhookEvent.php
apps/backend/Modules/Webhooks/database/migrations/2026_11_08_000001_create_webhook_events_table.php
apps/backend/Modules/Webhooks/database/migrations/2026_11_08_000002_create_webhook_endpoints_table.php
apps/backend/Modules/Webhooks/database/migrations/2026_11_08_000003_create_webhook_deliveries_table.php
apps/backend/tests/TestCase.php
docs/plans/multi-tenancy.md
```

</details>
