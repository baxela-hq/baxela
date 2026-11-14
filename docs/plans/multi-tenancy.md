# Multi-Tenancy Implementation Plan (rev 4.1)

Status: **plan — approved in substance by the architecture review** · Rev 4.1 folds in the five
targeted corrections from the final review (guest-safe idempotency identity, snapshot stack for
nested jobs, phase assignments for operational requirements, payment rotation/no-fallback precision,
concrete domain-verification protocol). Phase 1 may start; each phase requires the changed-file list,
static-analysis, and test results to be reviewed before the next phase is authorized.

Architecture: **single database, `tenant_id` row scoping** · host/subdomain tenant resolution ·
global users + per-tenant memberships · spatie-permission teams keyed by `tenant_id` ·
per-tenant payment credentials from day one · phased rollout, each phase leaves the suite green.

Framing adopted from the review: **a tenant is an independent business/store, not an independent
customer database.** Global customers shop across stores; staff memberships, roles, and permissions
are tenant-specific.

Rev 4 changes from rev 3 (architecture untouched):
- Central mode is entered only through a dedicated `CentralOperations` service (§6.5).
- Idempotency is redesigned as a concurrency-control mechanism with atomic reservation (§6.6).
- Queue context lifecycle uses snapshot/restore so nested (sync) dispatch cannot wipe the request
  context — verified against `SyncQueue` source (§6.1, F27).
- Intra-module tenant-composite FKs strengthen referential integrity without breaking module
  boundaries (§4.3).
- Payment configuration resolution is a named contract covering every existing client/driver,
  and the F11 "global paypal cache" classification is corrected (§6.3, F25).
- Missing requirements section: deletion, domain takeover, rate limiting, realtime, logging,
  exports, notifications, onboarding, search, backups (§9.5).
- Phase acceptance and test matrix extended; a final security acceptance gate added (§8, §10).

---

## 1. Confirmed codebase facts (verified) vs assumptions

**Verified facts** (file-checked):

- F1. No tenancy exists anywhere: no `tenant_id`/`store_id`/`team_id` columns; greps hit only a
  comment in `Modules/Order/app/Support/StoreTimezone.php` anticipating this change.
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
  table references violate the backend AGENTS.md module-boundary rule anyway.
- F8. Pre-v1 migration policy is explicit: `apps/backend/AGENTS.md` ("Database & Migrations"):
  edit original migrations in place; "No release has shipped yet". Root `DatabaseSeeder` is empty;
  dev DBs are disposable (`docker compose`, `migrate:fresh` sanctioned; compose `app` is sole
  migration owner via `RUN_MIGRATIONS=1`).
- F9. Raw queries that would **bypass** an Eloquent tenant scope (complete app-code list):
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
- F10. Queued work: 22 `ShouldQueue` classes (1 `Webhooks/Jobs/DeliverWebhookJob`, 1 Media listener,
  20 Notification listeners). None carry any store/tenant context today (verified: `DeliverWebhookJob`
  takes only `$deliveryId`; outbox tables have no tenant column).
- F11. Tenant-varying caches (complete list): `catalog.public.sitemap`, `content.public.sitemap`,
  `core.language.default_id`, `setting.timezone`, `setting.prices_include_tax`,
  **`payment.paypal.access_token`** (reclassified in rev 4: per-tenant under A2 — the token belongs
  to whichever tenant's PayPal credentials produced it, §6.3); invalidation only in
  `Modules/Setting/app/Actions/Admin/Setting/UpdateSettingAction.php:26-27`. Remaining global
  caches: `telegram:bot:username` (platform-level service, §9 R1).
- F12. Payment webhooks identify nothing tenant-ish today: `POST /webhook/{driver}` →
  `HandleWebhookAction` matches `payments.transaction_id` globally against driver-verified payload,
  using single global gateway credentials/secrets per driver.
- F13. Media: `$file->store()` with no path (`CreateMediaAction.php:35`) + `hashName()` — collision-safe
  names, shared root namespace, no per-tenant directory.
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
  is already a verbatim copy of the vendor stub.
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
  authorization are resolved per request. No tenant claim is embedded in the token.
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

**Confirmed owner decisions (gates closed):**

- A1. **Confirmed: no production database data must be preserved.** Editing original migrations in
  place is the selected path; the backfill recipe is retained only in Appendix A if this changes.
- A2. **Confirmed: payment gateway credentials are per-tenant from day one.** Phase 4/6 include
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

  `runForTenant` snapshots and **restores the previous mode in `finally`**. Under Octane the
  singleton persists across requests, so `runForTenant`'s snapshot/restore is not enough on its
  own: **every request must begin in `MODE_INACTIVE`** via an explicit reset (Octane
  `RequestReceived` listener or a scoped binding) so a previous request's tenant can never leak
  into the next one (F3 rev 5). Queue workers are handled in §6.1. `runCentral` is
  **removed** from the public surface in rev 4 — cross-tenant execution goes through
  `CentralOperations` (§6.5), which is the only caller of the internal `central()`.

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
- **`TenantScopedTrait`** (column constants) in `Modules/Core/app/Schemas/Shared/` — exports
  `TENANT_ID = 'tenant_id'` the same way `PkAndTimestampsTrait` exports shared columns. Core is the
  sanctioned exporter of shared schema traits, so modules never reference the Tenant module's schemas
  (respects the "Schemas are module-internal" rule).
- `Modules/Core/app/Contracts/Gateways/Tenant/TenantGatewayInterface` (+ DTOs) — Core-facing contract:
  `resolveByDomain(string $host): ?TenantSnapshot`, `find(int $id): ?TenantSnapshot`,
  `defaultTenant(): ?TenantSnapshot`, `isMembershipActive(int $userId, int $tenantId): bool`,
  `membershipsFor(int $userId): array`. Implemented by `Modules/Tenant/app/Gateways/TenantGateway.php`,
  bound in `TenantServiceProvider::$bindings` (house Gateway pattern).

---

## 3. Identification vs authorization — two middlewares

### 3.1 `ResolveTenant` — identification only (api group, unauthenticated)

Pushed in `CoreServiceProvider::boot()` right after `AssignRequestId` (F3). Order (audience-aware,
since route audience is knowable from the route name `api.{module}.admin.{user|public|webhook}.*`):

| Priority | Source | Honored for | Notes |
|---|---|---|---|
| 1 | `X-Tenant-Id` header | **admin audience only** | numeric; existence + status checked; authorization happens later (§3.2) |
| 2 | `X-Tenant-Host` header | public + user audiences only | storefront-forwarded host (§7); never honored on admin routes — a header can never override a storefront's domain identity |
| 3 | `Host` lookup in `tenant_domains` | all audiences | works when the API itself is served on a tenant domain |
| 4 | fallback host list | all | §3.3 — dev/legacy hosts only |
| 5 | — | — | **404** `core::errors.tenant_not_found` envelope |

Normalization before lookup: lowercase, strip port, strip trailing dot; reject IP-literal/`*.local`
mDNS hosts in production mode; IDN → punycode (`idn_to_ascii`).

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
| `admin` | resolved tenant must have an **active `tenant_memberships` row for the user** (§4.1), or user is platform super-admin (F5). Else **403** `core::errors.tenant_membership_required`. This is what makes `X-Tenant-Id: <someone-else>` fail closed. Membership revocation takes effect on the next request (no caching of membership beyond the request; §10 row 33). |
| `webhook` | n/a — see §6.3 (payment webhooks resolve tenant from the route slug + per-tenant secret) |

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
    'central_hosts'   => env('TENANCY_CENTRAL_HOSTS', ''),  // admin API hosts: host-resolution off
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
  `is_primary` bool, `verified_at` nullable timestamp. Custom domains stay inert until verified
  (§9.5 domain management: takeover protection, verification expiry, reassignment rules).
- `tenant_memberships` — id, `tenant_id` FK→tenants cascade, `user_id` plain indexed
  `unsignedBigInteger` (no cross-module FK; user existence is validated through
  `AccessGatewayInterface` / the User gateway), `status` enum(active|invited|suspended),
  `is_owner` bool, `joined_at`, timestamps, **`UNIQUE(tenant_id, user_id)`** (short index name
  per the 64-char rule).
  Membership ≠ authorization: membership answers *belongs to*, spatie answers *may do*.
  Platform super-admin authorization derives only from the NULL-team `SUPER_ADMIN` role; it never
  derives from membership. The seeded default-tenant owner membership exists only for ownership
  semantics (member management and ownership transfer).

Schemas (`TenantSchema`, `TenantDomainSchema`, `TenantMembershipSchema`), models, admin CRUD
Actions/Controllers/Requests/Resources under `api/v1/tenant/admin/...` (super-admin only — no
assignable permission; guarded by the same Gate::before), member-management endpoints
(list/invite/activate/suspend/remove), self-serve onboarding endpoint (user audience, §9.5),
`ErrorCodeEnum` + `lang/{ar,de,en,es,fa,fr}/errors.php`, `TenantDatabaseSeeder`. Domain CRUD and
membership writes execute through `CentralOperations` (§6.5).

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

### 4.3 Cross-tenant reference integrity (layered)

Layer 1 — scoped reads: all exists-checks (F14) flow through Eloquent models once those use
`TenantScopedTrait` → automatically tenant-filtered under MODE_TENANT. No signature changes at call
sites. `LanguageUniquePair` / `UniquePair` (F9) gain a tenant clause from `TenantContext`.

Layer 2 — validated writes: new Core helper `AssertsTenantOwnership` batch-checks supplied entity
ids through scoped gateway `exists` methods and rejects any id not owned by the active tenant.
Rules (rev 4, per review): validation runs **inside the same DB transaction as the write**; batch
validation completes **before any pivot synchronization or bulk insertion**. Applied to every write
path taking cross-entity ids without a FormRequest: Promotion products/categories, `post_product`,
`category_product` pivot syncs, Catalog importers, Transfer import/export, and directly callable
Actions. API FormRequests continue using scoped validation; background jobs inherit context per §6.1.

Layer 3 — intra-module composite FKs (rev 4 addition): **within a single module**, child tables add
a composite FK `(tenant_id, {parent}_id)` → `(tenant_id, id)` on the parent where practical
(e.g. Order items/returns/invoices → orders; Tax rates → tax classes; Catalog variants → products;
Webhook deliveries → endpoints/events). This gives DB-level cross-tenant protection for the most
common reference classes **without breaking module boundaries** (both tables live in the owning
module; the parent needs a `(tenant_id, id)` unique index to support the composite FK). Cross-module
references (promotions → products, posts → products) keep gateway validation only (F20 convention).

Layer 4 — audit: `tenancy:audit` uses a config-declared relation map (owning table → referenced
tables/columns, derived from module Schema constants, **including indirect relations through
pivots** — e.g. promotions→products via `promotion_products`). It reports both `tenant_id IS NULL`
rows and join violations where `owner.tenant_id <> referenced.tenant_id`. The map must cover every
declared cross-entity relation and evolve alongside schema changes.

Pivots between two tenant-owned tables (e.g. `promotion_products`) do **not** get their own
`tenant_id`; tenancy derives from the parent row. Tables joined by a global table
(e.g. `*_translations` keyed by `language_id`) get `tenant_id` on the owning entity only.

### 4.4 Tables that stay global

`tenants`, `tenant_domains`, `tenant_memberships`, `users`, `otp_codes`, `personal_access_tokens`,
all `auth_*` permission tables (they gain `tenant_id` as team key in §5 but rows are platform-level),
`languages`, `currencies`, `countries`/`regions`, framework tables (`cache*`, `jobs*`,
`failed_jobs`). `idempotency_keys` gains `tenant_id` + composite uniqueness (§6.6).
`webhook_deliveries`/`webhook_events` gain `tenant_id` (§6.2). Media rows are scoped; the shared
disk gets tenant subdirectories (§6.4).

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
  are seeder-written only (`updateOrCreate`). The existing `assertNotSuperAdmin` guard in
  `AbstractRoleAction` blocks admin role CRUD from creating NULL-team rows. A test asserts exactly
  one NULL-team row per platform role name (MySQL unique indexes treat NULL values as distinct).
- `AuthServiceProvider::Gate::before` becomes team-context-immune: directly query the user's role
  assignment by user id, join `auth_roles`, constrain role name to `SUPER_ADMIN`, and require
  `auth_roles.tenant_id IS NULL`. Do not rely on the active spatie team or the `roles()` relation's
  team filtering.
- Any attach of the platform role still writes a pivot team value. The platform seeder and
  `User::factory()->superAdmin()` must set `setPermissionsTeamId($defaultTenantId)` around the
  attach so the pivot's NOT NULL `tenant_id` is satisfied; restore the prior team context afterward.
- `AccessGateway::adminUserIds()` switches to a team-agnostic pivot-existence query; the `roles()`
  relation is no longer a safe all-team query after teams are enabled.
- Team context lifecycle: `AuthorizeTenantAccess` sets `setPermissionsTeamId($tenantId)` before
  permission checks and restores/clears it in a `finally`/request-termination path. Queue jobs that
  check permissions set it inside their tenant run. Under Octane the per-request lifecycle is no
  longer free: the team id must be cleared on request termination (spatie's octane reset listener
  is enabled via `config/permission.php`), so the static resolver stays safe (F3 rev 5).
  resolver safe (F3).
- Permission cache is confirmed team-agnostic: one flat cache key stores role/permission definitions;
  tenant filtering occurs in pivot SQL. No per-team cache key is required (but see §6.3 for the
  payment token cache, which IS tenant-scoped).
- Sign-in (`Modules/Auth/app/Actions/Public/Auth/SignInAction.php`) response gains `memberships`
  (from `tenant_memberships`, not roles) and `active_tenant` when unambiguous; Sanctum tokens remain
  global user credentials and tenant selection is per request.

---

## 6. Background execution, cache, storage, webhooks, idempotency

### 6.1 Queue lifecycle — snapshot/init/restore, both execution models

`Queue::createPayloadUsing` stamps every payload at dispatch time:
`{tenant_id: <id>|null, central: bool}` (`central=true` only when dispatched from MODE_CENTRAL).
This covers every dispatch origin (review requirement):

| Dispatch origin | Stamp | Why |
|---|---|---|
| HTTP request (tenant context) | tenant id | middleware resolved it |
| HTTP request (admin/central action) | central | `CentralOperations` context |
| Console command (scheduled/ops) | central or tenant | commands declare their run mode |
| Job dispatched from another job | parent's effective mode | stamp reads the live context at dispatch |
| Delayed / retried job | preserved | the stamp is part of the stored payload JSON |
| Seeder / migration | central | explicit |

Execution-side listeners (registered in `CoreServiceProvider`):

- **`JobProcessing`**: push the current context onto a **stack of snapshots** (rev 4.1: a single
  mutable snapshot would be overwritten by nested execution), then **deterministically initialize**
  from the payload markers: tenant id → MODE_TENANT; central flag → MODE_CENTRAL; neither →
  MODE_INACTIVE. No job ever inherits the previous job's residue, and a stale/leaked context is
  overwritten here regardless of cleanup failures.
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
clause. `PruneWebhooksCommand` declares itself a central console operation (§6.5).

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
mode/env, return-URL base, and a `credential_version` (incremented on config change). The
implementation (Payment module) resolves from `payment_gateway_configs` (tenant-scoped table:
`tenant_id`, `driver`, encrypted credentials cast, `webhook_secret` encrypted, `enabled`,
`credential_version`) via TenantContext — fail-closed when no tenant context exists. Platform
`.env` credentials serve only as local/test fallback, never in production.

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

Webhook routing: `POST /webhook/{tenant-slug}/{driver}` — resolve slug → reject suspended tenant →
verify signature with that tenant's secret → `runForTenant` for transaction lookup and outcome
writes. Return URLs derive from that tenant's verified domains. No tenant header is trusted on
webhook routes.

### 6.4 Media

Uploads move to `$file->store("tenants/{tenant_id}")` (hash names retained); media rows are
tenant-scoped; existing root-level files keep working (path is stored per row). Variant paths derive
from the original's directory — unchanged. Export artifacts (Transfer) are stored under the same
tenant prefix and downloadable only through tenant-scoped authorization (§9.5).

### 6.5 Central mode — dedicated service boundary (rev 4, per review)

`Modules/Core/app/Support/Tenancy/CentralOperations.php` is the **only** public entry to
MODE_CENTRAL:

```php
final class CentralOperations
{
    /** Run a registered cross-tenant operation. $operation must exist in the registry; $reason is audit-logged. */
    public function run(string $operation, string $reason, Closure $fn): mixed;
}
```

- The `$operation` registry is an explicit, reviewed list (config-constant in Core): tenant CRUD,
  membership writes, domain management, transfer import/export, webhook dispatch/prune bootstrap,
  `tenancy:audit`, seeders. Adding an entry is a visible diff reviewed like a permission grant.
- `$reason` is required and recorded: central admin operations emit an Audit event (F21 map) with
  actor, operation, reason; console operations log the command + reason.
- Exception safety: `run()` restores the previous mode in `finally`.
- `TenantContext::central()` is marked `@internal`; the architecture Pest test fails any direct use
  of `->central(`/`MODE_CENTRAL` outside `CentralOperations` + `TenantContext` itself, and fails any
  `CentralOperations::run()` call site whose `$operation` is not in the registry.
- Authorization: HTTP-reachable central operations sit on super-admin-only admin routes behind
  `PermissionMiddleware` + Gate::before; no request parameter, header, or user-controlled input may
  reach `run()`'s arguments. Tests prove ordinary tenant admins receive 403 and that context stays
  tenant/inactive after refused/failed attempts (§10 rows 21–22).

### 6.6 Idempotency — concurrency control, not just a response cache (rev 4, per review)

Redesign (Phase 4), fixing F16 + F26:

1. **Identity** (rev 4.1, review finding 1 — nullable `user_id` would let NULL duplicates through
   a MySQL unique index): edit `2026_03_25_092852_create_idempotency_keys_table.php` in place (A1):
   add `tenant_id` (NOT NULL), a **NOT NULL `principal_key`** string, and `status`
   enum(processing|completed); **replace the global `KEY` unique with
   `UNIQUE(tenant_id, principal_key, key)`**. `principal_key` identifies the caller for **every**
   request: authenticated users get `user:{id}`; guests get `guest:{sha256(X-Cart-Token)}` — the
   existing unguessable per-session UUID header, which is stable across legitimate retries (the
   client resends the same token) and is not forgeable into someone else's principal (a made-up
   token only loses the caller's own dedup protection; it grants access to nothing). `user_id`
   stays as a nullable traceability column; it is never part of the unique identity.
2. **Atomic reservation**: `IdempotencyMiddleware::handle()` inserts a claim row
   (status=processing, expires) **before** executing the operation. The insert relies on the
   composite unique constraint (all-NOT-NULL columns, so the guarantee holds for guests too —
   §10 row 39):
   - Insert succeeds → this request owns the key; proceed.
   - Unique violation → a concurrent/previous request holds it: if `completed`, replay the stored
     response (`X-Idempotency-Response: true`); if still `processing` (and not expired), return
     **409 `core::errors.idempotency_in_progress`** — never execute twice.
   - Expired processing row → reclaim (delete + re-insert, still constraint-guarded).
3. **Completion**: `terminate()` updates the claim with the response and status=completed (or
   deletes the claim on failure so a retry can start clean) — always scoped by tenant + principal.
4. Replay matching in `handle()` includes `tenant_id` + `principal_key` — cross-tenant and
   cross-principal replay are structurally impossible (§10 rows 11, 30, 39).

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
Pest in each phase; `vendor/bin/pint` on touched paths; full `composer test` once per phase.
Phase-by-phase approval: each phase ends with a changed-file list + analyse/test output for review
before the next phase starts.

### Phase 1 — Tenancy core + Tenant module (backend)

Files (new): `Modules/Tenant/**` (full module per §4.1, incl. onboarding endpoint stub wired in
Phase 6); `Modules/Core/app/Support/Tenancy/{TenantContext,CentralOperations,TenantSnapshot}.php`,
`Modules/Core/app/Models/Scopes/TenantScope.php`, `Modules/Core/app/Models/Traits/TenantScopedTrait.php`,
`Modules/Core/app/Schemas/Shared/TenantScopedTrait.php`,
`Modules/Core/app/Http/Middleware/{ResolveTenant,AuthorizeTenantAccess}.php`,
`Modules/Core/app/Contracts/Gateways/Tenant/**`, queue lifecycle listeners (snapshot stack), the
central-mode architecture test, and tenant log-context/Sentry tagging (§9.5 logging, rev 4.1
assignment). `Modules/Core/lang/*/errors.php` keys (`tenant_not_found`, `tenant_suspended`,
`tenant_membership_required`, `tenant_context_missing`; `idempotency_in_progress` lands with
Phase 4). Modified: `CoreServiceProvider` (middleware push + payload stamping + listeners),
`CoreDatabaseSeeder`. `config/permission.php` untouched this phase.

Tests: resolution matrix (rows 5, 8 public/user cells, 14), scope fail-closed unit tests (row 12),
context snapshot/restore incl. nested `runForTenant` (row 20), queue lifecycle in **both models**
(sync inline + `queue:work --once`, rows 26–28), tenant/domain/membership CRUD + non-super-admin
403, `CentralOperations` registry/reason/exception-safety + architecture test (rows 21–22),
default-tenant seeder assertion.

**Accept**: new tests green; **entire existing suite green with zero edits** (loopback fallback);
`composer analyse` clean; seeder produces default tenant + domain + owner membership.

### Phase 2 — Data scoping (backend bulk) — A1 confirmed; in-place migrations approved

Per-module batches (edit migrations in place + `TenantScopedTrait` on models + fillable +
factories + HelperTrait `seedTenant()`/`withTenant()`):

1. Commerce: Catalog (25 migrations incl. `*_translations`), Inventory, Cart, Order.
2. Ops: Tax (`ScopedRateUniqueRule` + `UniquePair`/`LanguageUniquePair` tenant clause), Shipping,
   Discount (pivot-sync `AssertsTenantOwnership` per §4.3), Payment (config table lands Phase 4;
   payments/payment_refunds data scoping here), Webhooks (tenant columns per §6.2).
3. Content: Content, Menu, Media (§6.4), Notification, Setting (settings rows per-tenant).
4. Engagement: User (addresses/wishlist), Support, Contact, Audit, Transfer (per-tenant; central
   exports via `CentralOperations`), Core `idempotency_keys` schema change lands with Phase 4.
5. Raw-query remediation list F9 closed item by item; intra-module composite FKs added (§4.3
   layer 3); `tenancy:audit` command with relation map incl. pivot relations.

**Accept**: rows 1–4, 12–13, 19, 23, 31 pass via two seeded tenants; every F9 site tenant-claused
or explicitly central-wrapped with a justifying comment; composite-FK violation test proves DB-level
rejection of a cross-tenant intra-module reference; `tenancy:audit` reports zero violations after
seeding; suite green.

### Phase 3 — Auth & permissions

Files: `config/permission.php` (teams), permission-tables migration (in place, §5),
`AuthServiceProvider` Gate::before pivot check, `AuthorizeTenantAccess` team-id set/reset,
`SignInAction` memberships payload, role CRUD per-tenant aware, role seeder per tenant.

**Accept** (rev 4 additions per review): rows 6–7; platform (NULL-team) role vs tenant role
separation — identical role names coexist across tenants; role/team switching on consecutive
requests; **membership revocation takes effect on the very next request** (row 33); existing
super-admin factory flows green.

### Phase 4 — Jobs, cache, webhooks, payments, idempotency

Files per §6: queue stamping + listeners (if not already in Phase 1); `DeliverWebhookJob`
bootstrap branches + `TenantAware` middleware; outbox tenant columns; `payment_gateway_configs`
migration + `PaymentConfigurationResolverInterface` + **full F25 client/driver/manager refactor**
(incl. atomic `credential_version` bumps and disabled-is-unusable enforcement, §6.3); PayPal token
cache scoping (§6.3); `POST /webhook/{tenant-slug}/{driver}` routing; suspended-tenant job skip +
audit; media store path; five cache keys + `UpdateSettingAction`; **idempotency redesign per §6.6**
(NOT NULL `principal_key`, composite unique, atomic reservation, 409 in-progress); **Reverb channel
migration to tenant-scoped names + broadcasting authorization checks** (§9.5 realtime);
**tenant-aware rate-limiter keys** (§9.5); **tenant-branded notifications** (§9.5).

**Accept**: rows 9–11, 17–18, 24–25, 29–32; concurrency test proves exactly one of two parallel
same-key requests executes (other 409 or replay); same key string reusable across tenants; tenant
signature routing accepts only the matching tenant secret; credentials/token caches never cross
tenants or credential versions.

### Phase 5 — Storefront

Files: `apps/storefront/lib/api/client.ts` (+ `server.ts`) forward `X-Tenant-Host`;
`lib/site-url.ts` per-request origin; sitemap/robots/metadataBase per host; payment return URLs
from tenant domains; settings/theme/menu per tenant.
**Accept**: two local hosts (`a.localhost`, `b.localhost`) render different tenants end-to-end
through compose; row 15 (guest cart isolation); **host-spoofing tests** — forged/`X-Forwarded-Host`
combinations cannot switch a user-route tenant identity (row 14); customer account works across
stores (global user, per-tenant carts/orders).

### Phase 6 — Admin panel

Files: `apps/admin/src/stores/tenant-store.ts`; `api-client.ts` `X-Tenant-Id` interceptor;
sign-in memberships; real `team-switcher.tsx` (switch = set tenant + `queryClient.clear()` +
router refresh); `src/features/tenants/` CRUD + members + onboarding UI; six locale files (en + fa
complete); storefront deep-links from active tenant domain; payment gateway config UI
(per-tenant credentials + webhook secret + enabled toggle).
**Accept**: row 16 verified by asserting refetch (not stale render) including rapid back-and-forth
switching (concurrent in-flight requests cancel/refetch — row 34); tenant CRUD + member management
+ onboarding round-trip; payment config UI reads/writes only the active tenant; locales en + fa.

### Phase 7 — Infra, Bruno, docs, security gate

Nginx passes Host already; document `*.localhost` dev hosts; `.env.example` gains
`TENANCY_FALLBACK_HOSTS`/`TENANCY_CENTRAL_HOSTS` (prod: empty) and payment vars marked
test-fallback-only; Bruno `Local.bru` tenant presets + `Tenant/Admin` folder; backend `AGENTS.md`
tenancy section (when to add `TenantScopedTrait`, `CentralOperations` registry rules, global-table
list, composite-FK rule); update `docs/PLATFORM_GAPS_ROADMAP.md`; regenerate module graph
(`python3 scripts/generate-module-graph.py`, commit `docs(monorepo): regenerate module dependency
graph`); tenant operations runbook (provisioning, suspension, domain verification, deletion,
rate-limit tuning).

**Final security acceptance gate** (rev 4, per review): an automated adversarial suite asserts
cross-tenant access is denied for products/categories, orders/payments, customer addresses, media
and exports, payment credentials, private realtime channels, and background-job records (rows 35–37),
run against a production-like config (empty fallback list).

**Accept**: fresh-clone bootstrap serves a working default-tenant storefront + admin with zero
tenant configuration; security gate green.

---

## 9. Additional risks, boundaries, and requirements

### 9.1–9.4 Risks

- **R1 — Platform-level services**: Telegram bot, Mail mailer, and Reverb app remain platform-level
  (A2 covers only payment gateways). Per-tenant mail sending identities (from-name/domain from
  tenant settings) ride the platform transport — §9.5 notifications.
- **R2 — Idempotency**: resolved by the §6.6 redesign (was: tenant column only).
- **R3 — Import/Transfer surfaces** (F9): high-risk raw-query sites; require `CentralOperations`
  or tenant-context wrapping plus `AssertsTenantOwnership` (Phase 2).
- **R4 — App-layer tenant integrity for cross-module references**: deliberate module-boundary
  trade-off; mitigated by scoped validation, in-transaction ownership asserts, intra-module
  composite FKs (§4.3), and the relation-map audit. Residual risk: an unreviewed raw write
  bypassing all layers; the architecture test and audit map must evolve with schema.

### 9.5 Missing requirements (rev 4, per review)

| Area | Requirement |
|---|---|
| Tenant deletion | Soft-delete is the default (storefront closes like suspension). Permanent deletion is a super-admin `CentralOperations` action with an audit reason: revokes memberships, detaches domains (after a configurable retention window), anonymizes rather than hard-deletes order/tax records where legally prudent. Retention/recovery window configurable; no automatic hard delete. |
| Domain management | Concrete protocol (rev 4.1, review finding 5): **verification is HTTP-first with DNS TXT as the alternate** — the admin generates a 32-byte random token per domain attempt (stored on the `tenant_domains` row with `verified_at` null); HTTP challenge = serve the token at `http://{domain}/.well-known/baxela-verification.txt`, DNS alternate = a `TXT` record `_baxela-verify.{domain}` containing the token. Tokens expire after a configurable TTL (default 48 h) and an unverified/expired domain resolves nothing (§3.1 lookup requires `verified_at`). Loss of control: verified status is re-checked opportunistically on super-admin action and can be force-revoked; a **released domain enters a cooling-off window (default 7 days, configurable — longer than common DNS TTLs) during which it cannot be attached to a different tenant**, after which the new owner must complete their own verification. A domain is attachable to at most one tenant (unique index) and reassignment always requires release + cooling-off + fresh verification. |
| Rate limiting | **Phase 4** (implementation): public/user rate limiters become tenant-aware (`by($tenantId.':'.$ip)`) so one tenant's traffic cannot exhaust another's quota; webhook limiter keyed by tenant slug + driver. **Phase 5** (verification): storefront-facing limiter isolation tested end-to-end (row 41). |
| Realtime | **Phase 4** (implementation, file-listed): channel names migrate to tenant scope (`private-tenant.{tenantId}.*`), the broadcasting authorization endpoint validates tenant identity + membership/customer status, and all `broadcasts()`/`Echo` channel references are updated with the rename; cross-tenant subscription by changing an identifier is denied (row 35). |
| Logging | **Phase 1** (implementation): `ResolveTenant` adds `tenant.id` to the request log context (extending the `AssignRequestId` pattern) and tags the Sentry scope when the SDK is active; logs never include credentials (Audit redaction, §6.3). Acceptance: log-context assertion in the Phase 1 middleware tests. |
| Exports | Transfer/export artifacts stored under `tenants/{id}/` and downloadable only through tenant-scoped authorization; export listings are tenant-scoped (row 37). Phases 2 (storage paths) and 7 (security gate). |
| Notifications | **Phase 4** (implementation): notification mailables/listeners render tenant identity (store name, from-name, storefront URL from tenant settings — resolved at send time inside the tenant run); transport stays platform-level (R1). Acceptance: notification content test asserts the owning tenant's branding. |
| Tenant onboarding | Self-serve workflow (user audience): sign up → `POST /api/v1/tenant/user/tenants` (rate-limited) → creator becomes owner (active membership + owner role for their tenant) → `<slug>` subdomain assigned + seeded default settings/menu → invite members. Super-admin CRUD remains for platform operations. |
| Search | Any future search index (external service or DB fulltext) must carry `tenant_id` and filter on it; recorded as a constraint for future work, not built here. |
| Backups | Per-tenant data export via the Transfer standard (already tenant-scoped); per-tenant restore is a follow-up decision, not in scope. |

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
| 8 | Suspended tenant matrix: public/user; active member admin; platform super-admin admin; queued jobs | public/user and active member admin → 403; super-admin full access/reactivate; queued jobs skipped + audit logged | 1/3/4 |
| 9 | Queued job runs for A | touches only A's rows | 4 |
| 10 | Cache read under A then B | no shared values (sitemap, settings keys) | 4 |
| 11 | Same idempotency key on A then B | no cross-replay; both usable independently | 4 |
| 12 | Tenant context missing (unit) | `TenantContextMissingException`, no query | 1 |
| 13 | Identical product name/slug in A and B | both allowed (tenant-scoped uniques) | 2 |
| 14 | `X-Tenant-Id`/`X-Forwarded-Host` on a public/user route | ignored — host identity wins | 1/5 |
| 15 | Guest cart token from A used against B | no access | 5 |
| 16 | Admin tenant switch (incl. rapid back-and-forth) | query cache cleared, lists refetch, no stale cross-tenant render | 6 |
| 17 | Payment webhook (signed, tenant slug route) | writes land in that tenant only | 4 |
| 18 | Media upload under A | stored under `tenants/{A}`, invisible to B | 4 |
| 19 | Import/export run per tenant | never crosses tenants | 2 |
| 20 | Context restore after nested `runForTenant` / central ops | previous mode exact-restored (incl. on exception) | 1 |
| 21 | Non-super-admin calls a central-mode route | 403; context unchanged | 1 |
| 22 | `CentralOperations::run` with unregistered operation / missing reason | throws; audit records nothing but the attempt logs | 1 |
| 23 | `tenancy:audit` sees a planted cross-tenant relation (direct + via pivot) | reports owner/reference tenant mismatch | 2 |
| 24 | Webhook signature with wrong/other tenant secret | rejected before any write | 4 |
| 25 | Payment config isolation | A cannot read/use B's credentials, secrets, or token cache | 4/6 |
| 26 | Sync (inline) dispatch inside a tenant request | job runs scoped; **request context intact afterward** | 1 |
| 27 | Real worker (`queue:work --once`, database driver) | context init from payload; restored between jobs; residue from a prior job cannot leak | 1 |
| 28 | Job dispatched with no tenant markers touches a scoped model | throws `TenantContextMissingException` | 1 |
| 29 | Delayed/retried job | payload markers persist; every attempt re-initializes | 4 |
| 30 | Two parallel requests, same tenant+user+key | exactly one executes; other gets replay or 409 in-progress | 4 |
| 31 | Same key string, different tenants (composite unique) | both succeed independently | 4 |
| 32 | PayPal token cache after credential rotation | new `credential_version` → fresh token, no reuse | 4 |
| 33 | Membership revoked mid-session | next request 403 (no stale authorization) | 3 |
| 34 | Rapid tenant switching in admin (concurrent in-flight requests) | responses never mixed across tenants | 6 |
| 35 | Cross-tenant private channel subscription | denied by channel authorization | 4 |
| 36 | Intra-module composite FK cross-tenant insert | rejected at DB level | 2 |
| 37 | Export artifact of A downloaded under B | denied | 2/7 |
| 38 | Security acceptance sweep (prod-like config) | all resource classes listed in Phase 7 gate deny cross-tenant access | 7 |
| 39 | Two simultaneous **guest** requests, same tenant + key + cart token | exactly one executes; other gets replay or 409 (NOT NULL `principal_key` closes the NULL-duplicate hole) | 4 |
| 40 | Nested sync jobs pinned to **different tenants** unwind in order | each `JobAttempted` pops its own snapshot; outer request/tenant context exact-restored | 1 |
| 41 | Rate limiter keyed per tenant | tenant A's saturation does not throttle tenant B | 4/5 |
| 42 | Disabled payment method invoked via direct driver/client call | resolver throws; no credential use | 4 |

---

## 11. Implementation-readiness assessment

All eight second-review findings are incorporated: (1) idempotency redesigned as concurrency
control with composite uniqueness and atomic reservation (§6.6); (2) every async dispatch origin
enumerated with no-inheritance and dual-model test requirements (§6.1); (3) layered integrity with
intra-module composite FKs, in-transaction validation, batch-before-sync, and pivot-aware auditing
(§4.3); (4) central mode behind `CentralOperations` with a registry, mandatory audit reason, and
exception-safe restore (§6.5); (5) the payment resolver contract covers every F25 config read-site
and the paypal-cache contradiction is fixed by tenant+version scoping (§6.3); (6) the queue
lifecycle works identically for sync and worker execution — verified against `SyncQueue` source,
with the rev-3 mid-request deactivation hazard fixed via snapshot/restore (F27, §6.1); (7) F2's
fail-open description corrected (IS NULL, not unscoped); (8) the idempotency unique constraint is
replaced, not extended (§6.6). The missing-requirements table (§9.5) and the Phase-7 security gate
(§8) close the operational gaps.

**Verdict: rev 4.1 is implementation-ready.** The review approved rev 4 in substance; rev 4.1
folds in its five targeted corrections: guest-safe idempotency identity (§6.6), the snapshot stack
for nested job execution (§6.1), phase assignments + tests for every operational requirement
(§9.5, §8), payment rotation/disabled/no-fallback precision (§6.3), and the concrete
domain-verification protocol (§9.5). Architecture decisions are unchanged. **Phase 1 is
authorized to start**; each subsequent phase requires the complete changed-file list,
static-analysis results, and test results to be reviewed before authorization.

### Appendix A — contingency only if A1 changes

Not applicable under the confirmed owner decision. If the owner later establishes that a database
with must-preserve data exists, stop before Phase 2 and redesign the migration sequence: create the
default tenant; add nullable `tenant_id` columns; backfill all existing rows to the default tenant;
then enforce NOT NULL and add indexes/tenant-scoped unique constraints. Do not execute this appendix
unless A1 is explicitly reversed and the migration plan is re-approved.
