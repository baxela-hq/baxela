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

## Transfer module API (admin audience, permission-derived from route names)

| Route | Purpose |
|---|---|
| `GET api/v1/transfer/admin/capabilities` | Coverage matrix: every enabled module, its sections and export/import flags. |
| `GET api/v1/transfer/admin/exports/{module}` | Streams the module's transfer file (`transfer-{module}-{timestamp}.json`). |
| `POST api/v1/transfer/admin/imports/preview` | Structure check of an uploaded file (envelope, sections, row cap, export-only warnings). |
| `POST api/v1/transfer/admin/imports` | Replays a file; dry runs validate without writing. Body: `file` (multipart JSON), `on_duplicate` (`update`\|`skip`), `dry_run`. |
| `GET api/v1/transfer/admin/imports` · `/imports/{id}` | Run history (persisted in `transfer_imports` with per-section counts, warnings, errors). |

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

## Rollout status

| Module | Status | Sections |
|---|---|---|
| Tax | ✅ export + import | `tax-classes`, `tax-rates` |
| Setting | ✅ export + import | `settings` (id-reference settings travel as codes) |
| Shipping | ✅ export + import | `zones` (country codes), `methods` (translations), `rates` |
| Discount | ✅ export + import | `coupons`, `promotions` (catalog ids via gateway), `redemptions` (export-only) |
| Contact | ✅ export + import | `newsletter-subscribers`, `contact-messages` (export-only) |
| Menu | ✅ export + import | `menus`, `menu-links` (parents-first tree) |
| Catalog | 🔀 separate implementation | Pre-dates the standard: `catalog/admin/data/*` (JSON module transfer) + product CSV import. Migration onto the standard is possible via an adapter. |
| Core | 📋 planned | `languages`, `currencies`, `countries` (reference data) |
| Content | 📋 planned | `pages`, `post-categories`, `posts` (translations) |
| User | 📋 planned | `addresses` (user refs are store-specific) |
| Inventory | 📋 planned | `stocks` (variant refs; usually derived) |
| Notification | 📋 planned | `preferences`, `push-subscriptions` |
| Order / Payment / Cart / Support | 🚫 export-only candidates | Transactional history — never replayed; import would break business invariants. |
| Media | 🚫 out of scope | Binary files are explicitly excluded from transfers; metadata export only, if ever. |
| Auth | 🚫 out of scope | Users/roles/permissions are security-sensitive; no bulk import surface. |
| Audit | 🚫 out of scope | Immutable log — export/import would forge history. |
