# 🔄 Module Data-Transfer Standard (Import/Export)

Every participating module can expose its **data** (never binary files — those live in
Media) for export and import through one platform standard. The
`Transfer` module orchestrates; each participating module describes and
replays its own rows.

```
Modules/Core/app/Contracts/Gateways/Transfer/ModuleTransferInterface.php   # the standard
Modules/{Name}/app/Transfer/{Name}Transfer.php                             # per-module implementation
Modules/{Name}/config/config.php  →  'transfer' => ['class' => ...]        # registration
Modules/Transfer/                                                          # engine + admin API
```

## Scope of the guarantee

The standard makes a backup of all **participating transferable business
data** restorable into an installation with **completely different
database ids** — preserving entity identity, historical records,
relationships and security boundaries. It is deliberately NOT a claim of
whole-install restoration:

- **Media** — binary files stay out (metadata export only, if ever).
  Business records referencing media ids keep the references; the files
  themselves must be migrated separately.
- **Auth** — credentials, roles and permissions are security-sensitive
  and never transfer. Imported users are inert placeholders (below);
  roles are not restored.
- **Audit** — an immutable log; importing it would forge history.
- **Cart & Notification** — ephemeral checkout state and runtime/device-
  bound notification data (sent-notification log, push/telegram
  subscriptions). Deliberately deregistered from the standard: no export,
  no import. Older v1 files declaring these modules are rejected
  explicitly (`module_mismatch`) — no silent success.

## The contract

A module implements `ModuleTransferInterface`:

| Method | Meaning |
|---|---|
| `sections(): TransferSection[]` | The module's row groups **in dependency order** — a section may only reference rows of sections before it. Each section flags itself `exportable` / `importable`. |
| `exportRows(string $section): list<row>` | Rows as `['source_id' => int, 'payload' => array]`. The payload carries the **exact JSON shape the module's own admin create/update endpoints accept** (or, for history modules, the faithful column export). |
| `importRows(string $section, array $rows, ImportOptions $options): ImportResult` | Replays rows through the module's invariants. Per-row failures are recorded, never thrown. |

### Payload rules

- **Translations carry language codes** (`'language' => 'en'`), never ids — the import resolves codes through `CoreGatewayInterface`.
- **Cross-record references carry the exporting store's source ids** (see *Reference resolution* below for how imports resolve them).
- **Runtime state stays behind** (counters like `usage_count` — recomputed from the imported dataset, never replayed).
- Rows failing validation are recorded as `"{section}[{n}]: {reason}"` on the `ImportResult` — one bad row never sinks a section.

### Dry runs

`ImportOptions` carries the duplicate strategy (`update` | `skip`), a
dry-run flag and the C2 resolution context. A dry run validates and
counts what *would* happen and writes **nothing** — including the remap
ledger. Within-file references resolve through **sentinel ids** (negative
ints). Cross-module references that only a *previous real import* could
satisfy are reported as warnings (`unresolved_in_dry_run`-class), so a
dry run's result classifies references as validated / deferred /
unresolvable — a preview with deferred required references is NOT a full
validation.

### Absent vs empty sections

Exporters emit **every declared section** (empty `rows: []` when no
data). On import: an **absent** section is a no-op (partial files
compose — the destination's rows for that entity stay untouched); an
**empty** section is authoritative emptiness (for the Order module's
child sections this means stale children are removed).

## Source-installation identity (C1)

Every installation has a stable public uuid (`transfer_installation`,
generated lazily on first export) stamped into the envelope's additive
`source_installation` key. v1 stays intact: legacy readers ignore the
key; legacy files (no key) import into the reserved `unidentified`
namespace.

- The uuid identifies this installation **when it exports** (outbound).
- `transfer_remaps` rows are keyed by the **source** installation of each
  import (inbound history) — they survive outbound rotation and database
  cloning on purpose: a cloned database also holds the local rows those
  mappings point at, so they stay valid.
- `transfer:reset-identity` rotates the outbound identity ONLY — the
  **mandatory clone-init step** after booting a database clone (snapshot,
  staging-from-prod, pre-populated image).
- `transfer:clear-remaps --source={uuid} --confirm` deletes ONE source
  namespace's mappings — the explicit escape hatch, never automatic.

## Reference resolution (C2)

Every cross-module reference resolves through the tiers, uniformly:

1. **Persisted remap** for the file's source namespace — the previous
   real import's ledger. A mapping pointing at a since-deleted local row
   is *dangling*: warned, never trusted.
2. **Natural key** where the payload carries one — normalized email
   (users), `order_code` (orders), `coupon_code` (coupons), `method`
   (payment methods), `uuid` (refunds), verified SKU (catalog products).
   Exactly-one semantics: zero matches → unresolved, one → resolve,
   multiple → ambiguity error. A natural key wins over a colliding raw
   id (warned).
3. **Raw source id** — legacy same-store tolerance. Automatic for
   **identified** files (valid `source_installation`); for legacy
   **unidentified** files it is gated behind the explicit
   `allow_raw_ids=true` request flag (default false → the reference
   fails/nulls with `ref_unresolvable_legacy`). Compatibility never
   silently sacrifices integrity.
4. **Column policy** — non-nullable identity refs fail the row; nullable
   loose refs null out with a warning.

Resolution diagnostics (`source_installation`, `raw_id_resolutions`)
land in every run's audit summary.

## Import concurrency & session safety (C7)

An import run holds a **destination-module named lock**
(`transfer:import:{module}`, bounded wait → `import_in_progress`): all
imports into a module serialize — whatever the source — because natural
keys are a shared destination namespace. The lock-owning database
session is proven before every statement (a `beforeExecuting`
ownership hook) and before every commit; a lost session is **fatal**
for the run (`lock_session_lost`, audited with the partial counts),
never a row failure, and importers re-prove ownership inside their
per-row catches so an action-level exception rewrap cannot mask it.
Rollbacks after session loss are best-effort and never mask the
classification. Retry from the beginning is always safe: every unit is
idempotent by remap/natural key. The ownership hook is registered once
per connection (a stable registry — repeated imports accumulate
nothing) and deactivates itself when the run ends; after teardown it is
inert for ordinary queries.

## Write-unit rules

- Entity write + remap record share one ownership-verified transaction
  (`TransferImportTransaction`) — a mapping and the row it describes are
  born together.
- Historical hydration (Order/Payment/Support/Redemptions) writes
  through the query builder: **no Eloquent events, no business
  actions**, no checkout paths — statuses, money snapshots and
  timestamps restored exactly as exported. Payment import is strictly
  inert: no gateway/driver code, no state transitions, no order updates.
- Per-row failures are isolated; the run continues. Order children are
  the one coarser unit: per (parent order × section), all-or-nothing,
  with children of failed/skipped parents never importing.

## Two-pass Discount restore

`redemptions` are order-linked history: pass 1 (before Order import)
skips them with the expected `pending_order_import` warning — that run
is incomplete BY DESIGN, not failed; pass 2 (after Order) resolves them
through the ledger. `usage_count` recomputes from the local dataset
after each run.

## Recommended whole-install import order

Core → Setting → Catalog → Inventory → Content → User → Tax → Shipping
→ Discount (pass 1) → Order → Payment → Menu → Contact → Support →
Discount (pass 2). Catalog before User/Content/Order so product/variant
references resolve; User before Order/Payment/Support so identity
remaps exist; Discount's second pass after Order closes the
redemptions.

## Security notes

- All `data.*` endpoints sit behind `auth:sanctum` +
  `PermissionMiddleware` with `{module}.admin.data.*` permissions —
  export grantable without import.
- Exports contain **PII** (user emails). Admin-only by permission;
  handle files accordingly.
- Imported user accounts are **inert placeholders** (inactive,
  unverified, random password, no roles): created for emails the
  destination has never seen, unusable until Auth's activation or
  verification flow runs. The gate is Auth's own (`is_active` re-check
  under a row lock at token issuance) — the import cannot weaken it.

## Registration

A module joins by declaring its implementation in **its own** config —
joining never touches the Transfer module:

```php
// Modules/{Name}/config/config.php
'transfer' => ['class' => \Modules\{Name}\Transfer\{Name}Transfer::class],
```

`TransferRegistry` iterates enabled modules and resolves
`config('{lower}.transfer.class')`. Modules without the key simply
don't participate.

## HTTP surface (admin audience, permission-derived from route names)

### Per-module data endpoints — `{module}/admin/data/*`

Available for every wired module, with permissions deriving as
`{module}.admin.data.*`:

| Route | Purpose |
|---|---|
| `GET api/v1/{module}/admin/data/export` | Streams the module's transfer file (`transfer-{module}-{timestamp}.json`). |
| `POST api/v1/{module}/admin/data/import/preview` | Structure check of an uploaded file (envelope, sections, row cap, export-only warnings). Rejects files declaring another module. |
| `POST api/v1/{module}/admin/data/import` | Replays a file; dry runs validate without writing. Body: `file` (multipart JSON), `on_duplicate` (`update`\|`skip`), `dry_run`, `allow_raw_ids` (legacy compatibility, default false). |
| `GET api/v1/{module}/admin/data/imports` | This module's run history from the central audit table. |

### Central endpoints — `transfer/admin/*`

| Route | Purpose |
|---|---|
| `GET api/v1/transfer/admin/capabilities` | Coverage matrix: every enabled module, its sections, export/import flags **and its published `endpoints` URLs** (route-derived; null for unwired modules). The admin UI reads this to call each module directly. |
| `GET api/v1/transfer/admin/imports` · `/imports/{id}` | Cross-module run history (the audit plane; runs from every per-module endpoint persist here). |

### The file envelope

One JSON file per module — `baxela.module-export` v1:

```json
{
    "format": "baxela.module-export",
    "module": "tax",
    "version": 1,
    "exported_at": "2026-11-07T10:00:00+00:00",
    "source_installation": "0c9a6e5f-…",
    "sections": [
        { "entity": "tax-classes", "rows": [ { "source_id": 1, "payload": { "...": "admin API shape" } } ] },
        { "entity": "tax-rates",   "rows": [] }
    ]
}
```

`source_installation` is additive-optional (C1). Row cap: 5000 total
rows per file (imports and exports both enforce it, keeping runs
synchronous and memory-bounded). Upload cap: 20 MB.

## Rollout status

| Module | Status | Sections |
|---|---|---|
| Tax | ✅ export + import · own endpoints | `tax-classes`, `tax-rates` — also exposed at `tax/admin/data/*` |
| Setting | ✅ export + import | `settings` (id-reference settings travel as codes) |
| Shipping | ✅ export + import | `zones` (country codes), `methods` (translations), `rates` |
| Discount | ✅ export + import | `coupons`, `promotions` (catalog ids via tiered resolution), `redemptions` (two-pass, see above) |
| Contact | ✅ export + import | `newsletter-subscribers`, `contact-messages` (export-only) |
| Menu | ✅ export + import | `menus`, `menu-links` (parents-first tree) |
| Catalog | 🔀 separate implementation | Pre-dates the standard: `catalog/admin/data/*`; imports record the remap ledger so other modules resolve catalog refs. |
| Core | ✅ export + import · own endpoints | `languages`, `currencies`, `countries` — upsert by code |
| Content | ✅ export + import · own endpoints | `pages`, `post-categories`, `posts` (catalog refs via tiered resolution) |
| Inventory | ✅ export + import · own endpoints | `stocks` (variant refs via tiered resolution) |
| User | ✅ export + import · own endpoints | `profiles` (the user DIRECTORY: email + `has_profile` + profile fields), `addresses` (region_code/vat_id since the e-mail-aware export), `wishlist-items` — identity by email; unknown emails become inert placeholder accounts |
| Order | ✅ export + import · own endpoints | `orders`, `order-items`, `order-addresses`, `order-returns` — immutable-history hydration; per-(order × section) atomicity; coupon refs via `coupon_code` |
| Payment | ✅ export + import · own endpoints | `payment-methods` (upsert by driver key), `payments` (insert-only, remap identity + exactly-one adoption via transaction tuple), `refunds` (uuid-keyed) — strictly inert |
| Support | ✅ export + import · own endpoints | `tickets`, `ticket-messages` — append-only history, remap-as-idempotency, no events |
| Cart | 🚫 deregistered | Ephemeral checkout state (guest tokens, consumed at checkout). Legacy files rejected explicitly. |
| Notification | 🚫 deregistered | Runtime/device-bound notification data (sent log, push/telegram subscriptions). Legacy files rejected explicitly. |
| Media | 🚫 out of scope | Binary files stay out of transfers. Future: a binary bundle format (a sidecar archive keyed by media ids) could close the gap — deliberately not built here. |
| Auth | 🚫 out of scope | Users/roles/permissions are security-sensitive; the only Auth surface in the standard is the gateway seam (`AccessGatewayInterface`: email directory + `ensureUserByEmail`), owned by Auth. |
| Audit | 🚫 out of scope | Immutable log — export/import would forge history. |
