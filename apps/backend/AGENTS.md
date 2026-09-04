# AGENTS.md

Guidelines for AI agents working on this codebase. Read this before making changes.

> **Maintaining this file:** when adding or updating a guideline, never paste the requester's words verbatim. Summarize the intent and rewrite it in clear, concise English, matching the style of the surrounding sections.

## Project Overview

**Baxela** — a developer-first, headless (API-only) e-commerce platform built as a **modular monolith** on **Laravel**. The backend serves a Next.js storefront and a React/Vite admin panel.

Guiding principles (from README):
- Modular monolith (`nwidart/laravel-modules`)
- Event-driven architecture
- Headless / API-first (no Blade UI is user-facing; everything goes through JSON APIs)
- SOLID & Open/Closed compliant

## Tech Stack

- PHP **^8.3**, Laravel **^13**, `nwidart/laravel-modules` **^12**
- Auth: `laravel/sanctum` (token auth)
- Query/filtering: `spatie/laravel-query-builder`
- API docs: `dedoc/scramble` (OpenAPI generated from code)
- Tests: **Pest** (`pestphp/pest`) + PHPUnit
- Code style: **Laravel Pint** (4-space indent, LF — see `.editorconfig`)
- DB: MySQL in dev (Docker), SQLite `:memory:` in tests
- Frontend tooling: Vite + Tailwind (used only for assets, not the product UI)

## Repository Layout

```
app/                        # Global app: base Controller, AppServiceProvider
bootstrap/app.php           # App config incl. global exception rendering
config/                     # Laravel config (incl. modules.php)
docs/                       # Architecture, flows, events, module templates
Modules/
  Core/                     # Shared infrastructure (schema traits, errors, events, gateways, middleware)
  Auth/                     # Users, OTP, auth flows
  Catalog/                  # Products, categories, options/option values, variants, images
  Cart/  Order/  Payment/   # Commerce flow
  Inventory/                # Stock
  User/                     # Profiles, addresses
  Media/                    # Files, folders
  Setting/  Content/  Notification/
tests/                      # Global Pest tests
```

Each enabled module is listed in `modules_statuses.json`. There is **no** `Identity` or `Pricing` module — docs files are partly aspirational; treat the actual `Modules/` directory as ground truth.

## Module Structure

Every module is self-contained. Its namespace root is `Modules\{Name}\` (autoloaded from `Modules/*/composer.json` via composer merge-plugin; `Modules\{Name}\Database\Factories\` and `...\Seeders\` map separately).

```
Modules/{Name}/
  app/
    Actions/{Admin|Public|User}/{Feature}/*Action.php   # business logic (handle())
    Http/Controllers/{Admin|Public|User}/{Feature}/*Controller.php  # invokable, thin
    Http/Requests/{Admin|Public|User}/{Feature}/*Request.php        # validation
    Models/*.php                                              # Eloquent models
    Schemas/{Entity}/*Schema.php                              # table/column constants
    Schemas/Module.php                                        # NAME, NAME_LOWER, DB_PREFIX, ROUTE_PREFIX
    Transformers/{Admin|Public|User}/{Feature}/*Resource.php  # JsonResource (API shape)
    Exceptions/ErrorCodeEnum.php, *Exception.php
    DTOs/  Gateways/  Repositories/
    Providers/{Module}ServiceProvider.php, EventServiceProvider.php, RouteServiceProvider.php
  database/
    migrations/  factories/  seeders/
  routes/api/{public,user,admin}.php      (or routes/api.php + require)
  lang/{en}/errors.php, seeder.php
  tests/Feature/  tests/Unit/
  config/config.php  module.json  composer.json
```

**The `Admin`/`Public`/`User` directory under `Actions`, `Http/Controllers`, etc. denotes API audience.** Keep this naming convention for new features.

## Non-Negotiable Conventions

### 1. Never hardcode table or column names — use Schema constants
Every table/column name is a constant in a `Schemas\...\*Schema` class. This is used consistently in models, migrations, requests, actions, seeders, and transformers.

- `Modules\{Name}\Schemas\{Entity}\{Entity}Schema.php` — `TABLE` (from `Module::DB_PREFIX`), column constants, and `RES_*`/`REQ_*` request/response keys.
- `Modules\{Name}\Schemas\Module.php` — `NAME`, `NAME_LOWER`, `DB_PREFIX` (e.g. `catalog_`), `ROUTE_PREFIX`.
- Shared constants come from traits in `Modules\Core\Schemas\Shared\`: `PkAndTimestampsTrait` (`ID`, `CREATED_AT`, `UPDATED_AT`), `SoftDeleteTrait` (`DELETED_AT`), etc.
- Enums are referenced as `Schema::FIELD => SomeEnum::class` for casts and `new Enum(SomeEnum::class)` in validation.

### 2. Thin invokable controllers → Action classes
Controllers are one-per-route `__invoke()` classes. They do three things only: inject an Action, validate via a FormRequest, return an API Resource.

```php
class CreateProductController extends Controller
{
    public function __construct(protected CreateProductAction $action) {}

    public function __invoke(ProductRequest $request): JsonResponse
    {
        return (new ProductResource($this->action->handle($request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
```

Business logic lives in Action classes with a single `handle()` method. Multi-step writes wrap `DB::beginTransaction()/commit()/rollBack()` and `report($e)` before re-throwing a module exception.

### 3. Error handling
- Each module defines `ErrorCodeEnum: string implements Modules\Core\Exceptions\ErrorCodeInterface` with dotted codes, e.g. `catalog.product.creation_failed`.
- Exceptions extend `Modules\Core\Exceptions\BaseException` (fields: `code`, `httpStatus`, `meta`, `isSafe`). E.g. `Modules\Catalog\Exceptions\Product\CreationFailedException`.
- `bootstrap/app.php` renders all JSON exceptions through `ExceptionMapper` → `ExceptionHelper::format_exception_response()`. The user-facing message resolves to `{module}::errors.{rest}` (see `Modules\{Name}\lang\en\errors.php`), falling back to `core::errors.500` when `isSafe = false`.
- Add a matching lang key in `Modules\{Name}\lang\en\errors.php` whenever you add an error code.

### 4. Cross-module communication
- **Events** (async/de-coupled): define the event contract in `Modules\Core\Contracts\Events\...`, dispatch it with `event(new XxxEvent(...))` from the Action. Other modules subscribe via their `EventServiceProvider`. The `Notification` module is the main consumer today.
- **Gateways** (sync reads): implement `Modules\{Name}\app\Gateways\*Gateway.php` against interfaces declared in `Modules\Core\Contracts\Gateways\...` (e.g. `CoreGatewayInterface::getActiveLanguages()`).
- Do **not** reach into another module's models directly for cross-module concerns.

### 5. Multi-language entities
Localizable entities (products, categories, options, option values) use a `*_translations` table (title/slug/content/description keyed by `language_id`).

- Requests use `Modules\Core\Http\Requests\ResolvesLanguagesTrait`, which maps the payload's `translations.*.language` code (e.g. `en`) → `language_id` automatically.
- Slug uniqueness per language uses `Modules\Core\Rules\LanguageUniquePair`; slug auto-slugifies via `Modules\Core\Models\Traits\SlugTrait`.
- Resolve languages via `Modules\Core\Models\Language` (seeded by Core). For admin requests needing language ids outside FormRequests, use `CoreGatewayInterface`.

### 6. API routing & auth
- Module `RouteServiceProvider` mounts everything under `api` middleware with `prefix('api')` + `name('api.')`.
- Module `routes/api.php` then groups under `prefix('v1'.'/'.$moduleRoutePrefix)` (e.g. `api/v1/catalog/...`, `api/v1/auth/...`), and is further split by audience:
  - `public.php` — no auth
  - `user.php` — `auth:sanctum` (any authenticated user)
  - `admin.php` — `auth:sanctum` + `Modules\Core\Http\Middleware\AdminMiddleware`
- Auth uses `RouteSchema` constants for paths; other modules use inline strings. Route names: `{module}.{area}.{entity}.{action}`.
- Token auth: `Auth::guard(...)->attempt()`, then `$user->createToken(...)->plainTextToken`.

### 7. One FormRequest per entity — shared by create and update
Create and update controllers inject the same request class. Never split validation into separate `Create*Request` / `Update*Request` files.

- E.g. `Modules/Media/app/Http/Requests/Admin/Folder/FolderRequest.php` backs both the create and update folder endpoints.
- For slug-bearing entities, tell the two cases apart inside `rules()` via the route's `{id}` parameter — `null` on create, the entity's id on update. Pass that id to `LanguageUniquePair` (when the slug lives on the translation schema) so the record being updated is exempt from the uniqueness check:

```php
$id = $this->route('id'); // null on create, entity id on update — /api/v1/catalog/admin/categories/{id}

CSchema::RES_TRANSLATIONS.'.*.'.CTSchema::SLUG => ['required', 'string',
    new LanguageUniquePair(CTSchema::TABLE, CTSchema::SLUG, $this->languageMap, $id)],
```

See `Modules/Catalog/app/Http/Requests/Admin/Category/CategoryRequest.php` for a full reference implementation.

### 8. Never use the `sometimes` validation rule
Request payloads must keep a fixed shape: every key is always present — its value may be `null` (use `nullable`), but the key itself can never be omitted. Because `sometimes` makes keys conditionally absent, it is forbidden in any new request. Leave existing requests that use it untouched. The only exception: a rare case where no alternative exists and the maintainer explicitly approves it.

## Database & Migrations

- Migrations live in `Modules\{Name}\database\migrations` (auto-loaded by the module's ServiceProvider). They use Schema constants for the table and every column.
- **Column removals/changes (pre-v1):** edit the original table migration in place instead of adding a `drop_*` migration. No release has shipped yet, so rewriting history keeps it clean, and the per-entity Schema classes (e.g. `CategorySchema`) make every affected reference easy to locate. **After v1 is published this flips** — schema changes must then go through new, dedicated migrations.
- Pivot/relational tables (e.g. `variant_option_values`) are created in the owning module.
- Soft-deleted entities use `SoftDeletes` and `SoftDeleteTrait` constants.
- Seeders are per-module under `database/seeders`; seed data for catalog uses lang files (`Modules\Catalog\lang\{en}\seeder.php`). Core seeds `languages`, `currencies`, `countries`.
- Run migrations with `php artisan migrate`; seed individual modules with `php artisan db:seed --class="Modules\{Name}\Database\Seeders\...Seeder"`.

## Testing

- **Do NOT add tests for new or updated endpoints (pre-v1).** The API is still unstable — request/response payloads are likely to change, so maintaining endpoint tests right now is wasted effort. Tests will be written once the logic stabilizes. (Temporary rule — will be lifted after v1 is published.)
- **Pest**. `phpunit.xml` auto-includes `Modules/*/tests/{Unit,Feature}` plus root `tests/`.
- Test files use `uses(TestCase::class)`, `uses(RefreshDatabase::class)`, and module-level `HelperTrait` (e.g. `Modules\Auth\Tests\Feature\HelperTrait::baseUrl()`).
- Tests run against SQLite `:memory:` (configured in `phpunit.xml`) — MySQL-only SQL or enums in `DB::statement` calls can break tests.
- Always run the full suite (`composer test` / `php artisan test`) and at least `vendor/bin/pint --test` after changes.

## Commands

```bash
# Dev stack is the root docker-compose.yml (run compose from the repo root)
docker compose up -d        # MySQL/Redis/nginx dev environment
docker compose exec app composer install
docker compose exec app php artisan migrate
composer test               # = config:clear + artisan test (Pest)
php artisan test            # run tests
vendor/bin/pint             # format code (use --test to check only)
composer dev                # server + queue + logs + vite (concurrently)
```

## Git Conventions

- Conventional commits: `type(scope): subject` — types observed: `feat`, `fix`, `refactor`, `chore`, `test`, `docs`, `build`, `ci`. Scope is the module or cross-cutting area: `catalog`, `auth`, `core`, `all`, `deps`, `infra`.
- Examples: `feat(catalog): implement products seeder`, `refactor(catalog): make multi-language products schema`, `chore(auth): add language & currency to response after sign-in`.
- Only commit when explicitly asked.

## Workflow for Adding a Feature (Checklist)

1. Identify the owning module (or create a new one via `php artisan module:make`).
2. Add `Schemas\{Entity}\*Schema.php` (+ `Module.php` constants) and a migration using them.
3. Add the model (with `$table`, fillable via schema constants, casts, relations, factory).
4. Add the FormRequest (schema-constant keys, `ResolvesLanguagesTrait` where needed).
5. Add the Action (`handle()`), using transactions for multi-table writes and throwing module exceptions.
6. Add the invokable Controller + Transformer resource.
7. Register the route in the correct `routes/api/{audience}.php` (and `require` it from `routes/api.php` if needed).
8. Add `ErrorCodeEnum`/exception + lang key if new failure modes exist.
9. Dispatch/define event contracts if other modules must react.
10. Run `composer test` and `pint` — but do **not** add tests for the new endpoint (see Testing).
