# Multi-Tenancy Phase 4 — Handoff to the implementing agent

You are continuing **Phase 4** of `docs/plans/multi-tenancy.md` (§8 Phase 4) on branch
`feat/multi-tenancy`. Phases 1–3 are done and verified. Phase 4 is roughly half done.
Read this document, the plan's §6.2–§6.7/§6.12/§9.7 and §8 Phase 4, and the Implementation
Record (end of the plan file) before writing any code.

## Ground rules (non-negotiable)

- The plan text is authoritative. Deviations are allowed but MUST be logged in the
  Implementation Record (end of `docs/plans/multi-tenancy.md`), same format as Phase 1–3's.
- Edit original migrations in place (A1). No release has shipped.
- Conventional commits, scope `backend/{module}` or `backend`. Commit after every verified
  batch and push to `origin/feat/multi-tenancy`.
- Verification gate per batch: `composer analyse` (must be exit 0), Pint clean on touched
  files, SQLite full suite (parallel ok), PostgreSQL full suite **serial only**
  (`DB_CONNECTION=pgsql DB_HOST=postgres DB_DATABASE=baxela_backend DB_USERNAME=baxela
  DB_PASSWORD=password`, `sh scripts/test.sh`). **Never run the PG plane under pest
  `--parallel`** — the four processes share one database and produce spurious failures.
- Prefix heavy commands with `nice -n 10` (dev machine is a 4-core laptop). Use the Docker
  Compose stack (`docker compose exec -T app sh -c '...'`); no host PHP.
- Do NOT check GitHub Actions — nothing is deployed.
- All work happens inside the existing tenancy architecture. Key facts you must not
  re-litigate:
  - `TenantContext` (Core) is the process-wide tenant state: MODE_INACTIVE (throw) /
    MODE_TENANT / MODE_CENTRAL (only via `CentralOperations`, registry-enforced).
  - **The spatie permission team IS the tenant context** — `TenantTeamResolver` reads
    `TenantContext::tenantId()`; never call `setPermissionsTeamId` anywhere. Pivot writes
    run inside `runForTenant($tenantId)`.
  - Platform-role (super-admin) reads go through `PlatformRoleAssignment` (morph- and
    guard-disciplined, team-immune) — never through the team-filtered `roles()` relation.
  - `tenant_id` for bulk/query-builder writes comes only from `TenantWriteStamp::
    tenantIdForWrite()`; raw chains get `TenantQueryScope::apply()`. The query-site and
    validation-rule allowlists live in `Modules/Core/config/config.php`
    (`core.tenancy.query_sites`, `validation_rules`) and are checked per-site by
    architecture tests — **if you add or remove a `DB::table` chain or a bare
    `unique:`/`exists:` rule on a store-owned table, update the allowlist**.
  - `tests/TestCase.php` sets every test's context to tenant 1 and flushes the spatie
    permission cache per test. Tests needing a real default-store membership call
    `$this->withDefaultStoreMembership($user)` (seeds the default tenant row exactly as
    `TenantDatabaseSeeder` does, then aligns the context to it).
  - phpstan baseline: if you touch signatures, regenerate with
    `vendor/bin/phpstan analyse --generate-baseline` ONLY when the new errors are
    genuinely unavoidable, and say so in the record. Phase 4's current baseline has 485
    entries; don't let it grow casually.
  - Mockery partial mocks of classes that gained constructor args need the args passed:
    `Mockery::mock(X::class, [$arg])->makePartial()`.

## Done in Phase 4 (committed; do not redo)

### Part 1 — Webhooks (§6.2, §6.7.2), commit `d9db53da`

- Two-stage bootstrap sweep: `DiscoverDueDeliveriesOperation` (registered central
  operation, reads only) → per-delivery `runForTenant` dispatch so the job payload carries
  the store. `DeliverWebhookJob` has the `TenantAware` middleware (`Modules/Core/app/
  Queue/TenantAware.php`), the central-dispatch bootstrap lookup
  (`LookupWebhookDeliveryOperation`), and the suspended-tenant skip, audited through
  `WebhookDeliverySkippedEvent` (F21 map).
- `finalize()` reads through the scoped model. `webhooks:retry-delivery` is bootstrap
  mode; `webhooks:prune` is tenant-fan-out (models, not DB::table).
- `command_modes` in Core config declares exactly the commands whose own source enters
  central mode (`tenancy:audit`, `webhooks:retry-delivery`). The full §6.7.2 declaration
  table is Phase 5 work — do NOT pull it forward; the architecture test
  (`TenantCommandModeArchitectureTest`) checks the exact symmetry "registered ⟺ the
  command file contains CentralOperations".

### Part 2 — Payments (§6.3), commit `5c22a71f`

- `payment_driver_credentials` gained `enabled` (bool) + `credential_version` (bigint,
  **monotonic millisecond stamp** — survives full deletion, unlike an increment).
- New Core contract `PaymentConfigurationResolverInterface` + `PaymentConfiguration` DTO
  + `PaymentConfigurationMissing` exception. Implementation:
  `Modules/Payment/app/Support/ResolvesPaymentConfiguration.php`.
- `ResolvesPaymentCredentials` reworked: per-(tenant, driver) cache
  `{cachePrefix}payment.credentials:{driver}:{credential_version}`; version read UNCACHED;
  version 0 = no rows; tenancy exceptions rethrown (row 45); `driverDisabled()` answers
  the enabled flag.
- `UpdateDriverCredentialsAction` bumps the version in the same transaction as the write
  AND the delete; the afterCommit cache-flush (config-era) is gone — the version bump
  rekeys everything.
- The F25 refactor is COMPLETE: all clients (Stripe, PayPal, Razorpay, Nowpayments,
  Mercadopago, Checkoutcom, Adyen) and all drivers read credentials through the injected
  `PaymentCredentialResolverInterface` (`$this->configs->credential('driver', 'key')`) —
  config('payment.*') reads are gone from Gateways/. Unit tests construct drivers as
  `new XDriver(new ResolvesPaymentCredentials, $client)`.
- `PaymentDriverManager::isConfigured` reads the scoped resolver + the disabled rule
  (row 42). **`ApplyDatabaseCredentials` is DELETED** (middleware, provider registration,
  bootstrap entry) — config is no longer the credential state. The api-group priority
  list no longer references it (`RouteAudienceTest` updated).
- PayPal token cache keyed `{cachePrefix}payment.paypal.access_token:{credential_version}`
  (row 32).
- Webhook route: `POST api/v1/payment/webhook/{tenantSlug}/{driver}` (§3.1 B2). Controller
  signature `__invoke(string $tenantSlug, string $driver, Request $request)` — Laravel
  injects route params in URI order. Webhook tests resolve the seeded default store
  (`slug: default`) — Payment's HelperTrait seeds it for `/webhook/` endpoints.
- Removed `ResolvesPaymentCredentials::CACHE_KEY`; `TestCase::setUp` no longer forgets it.
- 4 middleware-era tests in `DriverCredentialsTest` were rewritten to the resolver
  contract (no more `config('payment.stripe.secret')` assertions).

**Verified at commit 5c22a71f**: analyse clean, Payment suite 352 passed, SQLite full
suite 8 skipped / 1657 passed. **PostgreSQL plane NOT yet re-run after 5c22a71f — your
first task is the full two-plane verification of the committed state.**

## Your work, in order

### 0. Verify the inherited state (~30 min)

Run analyse, Pint, the SQLite suite and the serial PostgreSQL suite. Everything must be
green before you build. If the PG plane shows failures, fix them first (the Phase 3
lesson: run serially; investigate with a held-row-lock probe only after confirming the
failure reproduces serially).

### 1. Batch 4d — Idempotency redesign (§6.6) — do this SECOND (it is self-contained)

Edit `Modules/Core/database/migrations/2026_03_25_092852_create_idempotency_keys_table.php`
in place. §6.6 specifies the full design; the essentials:

- Columns: `tenant_id` (already there), NOT NULL `principal_key` (user:{id} or
  `guest:{sha256(X-Cart-Token)}`), `status` enum(processing|completed), `claim_token`
  uuid, `expires_at`, `committed_at` nullable; unique becomes
  `UNIQUE(tenant_id, principal_key, key)` — note Phase 2 already replaced the global
  `KEY` unique with `UNIQUE(tenant_id, key)` (deviation 6); widen it now.
- `IdempotencyMiddleware` (`Modules/Core/app/Http/Middleware/` — find it) gets the atomic
  reservation (INSERT … ON CONFLICT DO NOTHING), 409 `idempotency_in_progress`, fenced
  reclamation (conditional DELETE + guarded INSERT, `committed_at IS NULL` guard),
  completion in `handle()` (not terminate), the commit-marker helper that throws on
  zero-row updates, and `idempotency_unresolved` on lost ownership. Every deletion path
  keeps the `claim_token` + `committed_at IS NULL` guard.
- Tests: rows 30, 31, 76, 77, 78, 88, 89, 97, 111, 114. Rows 30/77/114 need two
  PostgreSQL connections — copy the pattern of
  `Modules/Tenant/tests/Feature/Isolation/OnboardingQuotaConcurrencyTest.php`
  (DatabaseMigrations, pgsql-gated, own file).
- Row 31 (same key in two tenants) is partly proven by Phase 2's
  `Phase2SchemaInvariantsTest`; keep it passing.

### 2. Batch 4e — Store runtime (§6.7) + platform settings (§6.7.1 last bullet)

- `ResolveTenant` already raises `TenantResolved` (Core contract event). Add the Setting
  module listener that applies the store runtime per request: timezone
  (`date_default_timezone_set` + `app.timezone`), store identity (`config('store.*')`),
  default language/supported locales, enabled currencies + display currency (P2), pricing
  mode, default tax class, FX values, mail from-name. `SettingServiceProvider::boot()`
  must stop applying anything per process (§6.7 items 1–2).
- The seven F38 `catch (Throwable)` sites (`AppliesStoreTimezone`,
  `AppliesStoreIdentity`, `ResolvesPricingMode`, `ResolvesFxSettings` ×3,
  `SupportedLocales`) rethrow tenancy exceptions; the Payment-side two are already done.
- Every store-dependent cache key goes through `TenantContext::cachePrefix()`
  (`setting.timezone`, `setting.store_identity`, `setting.fx`, `setting.prices_include_tax`,
  `catalog.public.sitemap`, `content.public.sitemap`, `core.language.default_id`;
  installation-level keys unchanged). Write the 18-key disposition table into the record
  (§6.7 item 6 — the Phase 4 entry artifact).
- Platform-settings read path: Core contract `PlatformSettingsInterface::get(name)` over
  an allowlisted platform names set (FX provider + key, platform identity), reading
  NULL-tenant `setting_settings` rows directly, cached `platform:setting.{name}`,
  invalidated in the write path; missing/malformed → `PlatformSettingMissing` (rows 81/93).
  Tenant-facing code NEVER enters central mode for this.
- P5 in place: platform-wide names (fx_*, platform identity) move to NULL-tenant rows;
  store rows keep everything else (§6.7.1; `setting_settings` is platform tier —
  `(tenant_id, name)` + partial `(name) WHERE tenant_id IS NULL` unique already exist
  from Phase 2).
- Tests: rows 10, 64, 81, 93.

### 3. Batch 4f — Notifications, rate limits, Reverb, K6, K7 (§6.12, §9.7)

- Tenant-branded notifications (row 63): templates read the active tenant's name/URL, not
  `config('app.name')`. AdminRecipients constrained to the context tenant's staff.
- Tenant-aware rate limiter keys (row 65): the 12 limiters of F37 key by tenant where the
  route is tenant-audience (`auth-sign-in` etc. stay per-IP; `cart-public`,
  `contact-submit`, `contact-subscribe`, `support-ticket-*`, `webhook` get the tenant in
  the limiter key via `TenantContext`).
- Reverb/broadcast channels tenant-scoped (§9.7 realtime): channel names carry the
  tenant; broadcasting authorization checks membership.
- K7: register `NotificationCreated` in `Modules/Core/config/dispatch.php`.
- K6: `MediaGateway::upsertLocal` takes the acting user explicitly (no hard-coded user 1).
- Tests: rows 63, 65.

### 4. Batch 4c — Media (§6.4) — do this LAST (largest, most concurrent)

Read §6.4 in full before starting — it went through five review rounds (rev 4.5–4.10) and
every detail is load-bearing. Summary of the build order:

1. Migration (one file, in-place style): the three coordination tables —
   `media_object_locks` (lease: `UNIQUE(tenant_id, object_key)`, `owner_token`,
   `generation`, `expires_at`), `media_deletion_intents` (`owner_token`, `generation`,
   `status` enum(pending|executing|done|cancelled), partial unique
   `ON (tenant_id, object_key) WHERE status IN ('pending','executing')`),
   `media_object_generations` (`UNIQUE(tenant_id, object_key)`, `status`
   enum(eligible|retired), never pruned) + the engine-branched trigger rejecting
   `retired → eligible` (§6.8 pattern — copy the immutability trigger migration's
   pgsql/sqlite branches). Register the tables in `TenantTableInventory` (store-owned) —
   row 113's trigger gate then covers them automatically.
2. Per-store physical keys: `media/{tenant_id}/{ab}/{sha256}-{ulid}.{ext}`; media rows
   store the exact physical key; staging stays `.staging/media/{uuid}.{ext}`; trash on a
   dedicated private `trash://` disk (storage config + filesystems.php; row 121 tests
   direct HTTP + URL generator).
3. The publication path: generation-first protocol (§6.4 rev 4.10) —
   `SELECT … FOR UPDATE` the `media_object_generations` row, verify `eligible`, hold the
   lock through the reference-insert transaction; a new physical object is fully written
   before any reference commits.
4. Deletion: lease acquisition → T1 (recheck references, insert intent `pending`, mark
   generation `retired`) → T2 (`FOR UPDATE` the lease row, re-verify owner+generation+
   expiry, transition to `executing`, unlink/rename inside the open transaction) →
   finalize `done`. Recovery via `media:outbox:sweep` two-stage (§6.7.2, rev 4.8/4.9:
   central discovery returns `(tenant_id, record id, kind)` hints; every item reloads
   under `runForTenant`, revalidates, and uses only the validated record's exact key).
   Publication against an active intent resolves it first.
5. `media_media_outbox` gains `tenant_id` (column may already exist from Phase 2 — it
   does; verify the processor derives the key from the row).
6. Tests: rows 61, 79, 91, 99, 100, 112, 116, 117, 120, 121, 122, 123. Rows 120 and 123
   are deterministic two-PostgreSQL-connection races (pause/resume via a second
   connection holding/breaking locks) — dedicated isolation files, pgsql-gated, like
   `OnboardingQuotaConcurrencyTest`. Row 123 asserts BOTH orderings and the final
   database + physical-object state.

### 5. Record, verify, ship

- Update the Implementation Record's Phase 4 section: replace the "IN PROGRESS" preamble
  (plan line ~2044) with the finished-phase record — what landed per batch, every
  deviation with its reason, final verification numbers for both planes.
- Final gates: `composer analyse`, Pint on touched files, SQLite suite, serial PostgreSQL
  suite — all green.
- Commit(s): conventional, `feat(backend/{module})`; push after each verified batch AND
  at the end. Do not commit the plan-file record separately from its batch.

## Acceptance (from §8 Phase 4 — all must pass)

Rows 9–11, 17–18, 24–25, 29–32, 42, 45, 61, 63–65, 76–79, 81, 88–89, 91, 93, 97,
99–100, 111–112, 114, 116–117, 120–123 and 113 (the media tables' immutability
triggers). Concurrency proofs: exactly one of two parallel same-key requests executes
(other 409 or replay); same key string usable across tenants; tenant signature routing
accepts only the matching tenant secret; credentials/token caches never cross tenants or
credential versions; two stores served in sequence by one process see only their own
store runtime (§6.7).

## Current branch state (at handoff)

- `origin/feat/multi-tenancy` at `5c22a71f` — clean tree, everything committed.
- Known-good: analyse exit 0, Payment 352 passed, SQLite 1657 passed / 8 skipped.
- PG plane after 5c22a71f: not yet run (your step 0).
- Open carries from earlier phases (documented in the record, no action needed now):
  the store-currency setting still mirrors into `core_currencies.is_default`
  (Phase 3 deviation 7 — resolved by 4e's §6.7 resolver, i.e. YOUR batch 4e);
  K8 ceased with the php-fpm revert.
