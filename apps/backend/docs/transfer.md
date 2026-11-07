# 🔄 Module Data-Transfer Standard (Import/Export)

Every module can expose its **data** (never binary files — those live in
Media) for export and import through one platform standard. The
`Transfer` module orchestrates; each participating module describes and
replays its own rows.

```
Modules/Core/app/Contracts/Gateways/Transfer/ModuleTransferInterface.php   # the standard
Modules/{Name}/app/Transfer/{Name}Transfer.php                             # per-module implementation
Modules/{Name}/config/config.php  →  'transfer' => ['class' => ...]        # registration
Modules/Transfer/                                                          # engine + admin API
```

## The contract

A module implements `ModuleTransferInterface`:

| Method | Meaning |
|---|---|
| `sections(): TransferSection[]` | The module's row groups **in dependency order** — a section may only reference rows of sections before it. Each section flags itself `exportable` / `importable` (transactional data ships export-only). |
| `exportRows(string $section): list<row>` | Rows as `['source_id' => int, 'payload' => array]`. The payload carries the **exact JSON shape the module's own admin create/update endpoints accept**. |
| `importRows(string $section, array $rows, ImportOptions $options): ImportResult` | Replays rows (remap ids, resolve language codes, match by natural key, then create/update **through the module's own admin Actions** so invariants and events behave like a manual save). Per-row failures are recorded, never thrown. |

### Payload rules

- **Translations carry language codes** (`'language' => 'en'`), never ids — the import resolves codes through `CoreGatewayInterface`.
- **Cross-record references carry the exporting store's source ids** (`source_id` / foreign keys inside the payload). Portability comes from the importer's remapping, not from the export. References into *other* modules (e.g. promotion → catalog products) are validated through that module's **gateway** (`productExists()`, …), and rows pointing at records the importing store lacks fail per-row instead of guessing.
- **Runtime state stays behind** (counters like `usage_count`). Configuration travels; history does not get replayed.
- Rows failing validation are recorded as `"{section}[{n}]: {reason}"` on the `ImportResult` — one bad row never sinks a section.

### Dry runs

`ImportOptions` carries the duplicate strategy (`update` | `skip` — what
happens to a row that matches an existing record by its natural key)
and a dry-run flag. A dry run validates and counts what *would* happen
(created/updated/skipped) and writes nothing; references to rows the run
itself would create resolve through **sentinel ids** (negative ints),
so a file is previewable on an empty store.

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

> **Post phase-3/4 state.** File export/import live in each module's own
> routes; the central Transfer module is the registry + audit plane only.

### Per-module data endpoints — `{module}/admin/data/*`

Available for every wired module (`tax`, `setting`, `shipping`,
`discount`, `contact`, `menu`), with permissions deriving as
`{module}.admin.data.*`:

| Route | Purpose |
|---|---|
| `GET api/v1/{module}/admin/data/export` | Streams the module's transfer file (`transfer-{module}-{timestamp}.json`). |
| `POST api/v1/{module}/admin/data/import/preview` | Structure check of an uploaded file (envelope, sections, row cap, export-only warnings). Rejects files declaring another module. |
| `POST api/v1/{module}/admin/data/import` | Replays a file; dry runs validate without writing. Body: `file` (multipart JSON), `on_duplicate` (`update`\|`skip`), `dry_run`. |
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
    "sections": [
        { "entity": "tax-classes", "rows": [ { "source_id": 1, "payload": { "...": "admin API shape" } } ] },
        { "entity": "tax-rates",   "rows": [] }
    ]
}
```

Row cap: 5000 total rows per file (imports and exports both enforce it,
keeping runs synchronous and memory-bounded). Upload cap: 20 MB.

## Architecture decision: per-module endpoints (microservice readiness)

> **Decided 2026-11-07 · phases 1–4 complete (2026-11-07).** Each module exposes its own
> import/export HTTP surface, so extracting a module to a microservice
> carries its data, its transfer logic *and* its endpoints — no central
> dependency beyond the shared format contract.

### Why

- The data plane (`{Name}Transfer`) already lives inside each module;
  the HTTP surface is the remaining coupling to the central Transfer
  module.
- Per-module routes yield granular permissions (`tax.admin.data.export`
  grantable without import) and self-documenting Scramble surfaces under
  each module's own prefix. Catalog already proves the shape with its
  pre-standard `catalog/admin/data/*` endpoints.

### Target shape

| Concern | Home |
|---|---|
| Envelope encode/decode (`TransferFormat`, `TransferFile`) | `Modules/Core/app/Support/Transfer/` — format-level, shared; graduates into the `baxela/contracts` package per the extension roadmap |
| Export/import/preview/history controllers | Core base controllers doing the whole file plane; each module ships ~15-line subclasses binding `module()` + `transfer()` |
| Routes | Each module's `routes/api/admin.php` — `data.export`, `data.import`, `data.imports.*`; permissions derive as `api.{module}.admin.data.*` |
| Capabilities matrix | Stays central in the Transfer module, extended with each module's endpoint base URLs so the admin UI stays one page |
| Run history | Central `transfer_imports` as the audit plane for now; an extracted service posts its runs there (or keeps its own) |

Deliberately **not** done: duplicating envelope/history machinery per
module (the Core base layer prevents it), and moving discovery into the
modules (a registry is inherently cross-cutting — microservice estates
still keep a service catalog).

### Phase plan

1. [x] Move envelope support to Core; add Core base export/import controllers; wire **Tax as the pilot** (`tax/admin/data/*`), keeping the central endpoints working. *(Done 2026-11-07: envelope, run model, resources, requests and exceptions live in Core now, with `ModuleTransferRunner` as the shared file plane and `core.transfer.*` as the policy config.)*
2. [x] Roll the base out to the remaining Wave-1 modules (mechanical). *(Done 2026-11-07: Setting, Shipping, Discount, Contact and Menu all expose `{module}/admin/data/*` with `data.imports` history scoping.)*
3. [x] Capabilities payload publishes per-module `endpoints`; the admin UI's transfer page switches to those URLs. *(Done 2026-11-07: URLs resolve from the registered routes and the import card routes uploads by the file's declared module.)*
4. [x] Central Transfer file endpoints removed — the central module is capabilities + audit history only; their generic guard coverage moved to the Tax pilot tests.

## Rollout status

| Module | Status | Sections |
|---|---|---|
| Tax | ✅ export + import · own endpoints | `tax-classes`, `tax-rates` — also exposed at `tax/admin/data/*` (phase-1 pilot) |
| Setting | ✅ export + import | `settings` (id-reference settings travel as codes) |
| Shipping | ✅ export + import | `zones` (country codes), `methods` (translations), `rates` |
| Discount | ✅ export + import | `coupons`, `promotions` (catalog ids via gateway), `redemptions` (export-only) |
| Contact | ✅ export + import | `newsletter-subscribers`, `contact-messages` (export-only) |
| Menu | ✅ export + import | `menus`, `menu-links` (parents-first tree) |
| Catalog | 🔀 separate implementation | Pre-dates the standard: `catalog/admin/data/*` (JSON module transfer) + product CSV import. Migration onto the standard is possible via an adapter. |
| Core | ✅ export + import · own endpoints | `languages` (default flag demotes others), `currencies`, `countries` — upsert by code, mirroring the seeder |
| Content | ✅ export + import · own endpoints | `pages`, `post-categories` (parents-first tree), `posts` (translations, SEO, catalog ids via gateway) |
| Inventory | ✅ export + import · own endpoints | `stocks` (variant ids via gateway; replay through `upsertStock`) |
| User | ✅ export-only · own endpoints | `profiles`, `addresses`, `wishlist-items` — user refs are store-specific, so no import |
| Notification | ✅ export-only · own endpoints | `notifications`, `notification-preferences`, `push-subscriptions`, `telegram-subscriptions` — per-user/per-device state |
| Order | ✅ export-only · own endpoints | `orders`, `order-items`, `order-addresses`, `order-returns` — faithful attribute export; replaying would forge history |
| Payment | ✅ export-only · own endpoints | `payment-methods`, `payments`, `refunds` — the money trail never replays |
| Cart | ✅ export-only · own endpoints | `carts`, `cart-items` — ephemeral storefront state |
| Support | ✅ export-only · own endpoints | `tickets`, `ticket-messages` — per-user correspondence |
| Media | 🚫 out of scope | Binary files are explicitly excluded from transfers; metadata export only, if ever. |
| Auth | 🚫 out of scope | Users/roles/permissions are security-sensitive; no bulk import surface. |
| Audit | 🚫 out of scope | Immutable log — export/import would forge history. |
