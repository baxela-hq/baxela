# Commit Message Convention

This project uses the format:

```
<type>(<scope>): <subject>
```

`<scope>` is **mandatory**. It is the primary way to signal *where* a change happened — critical in a monorepo that bundles multiple apps and, later, when apps are split into independent repositories.

## Format

```
<type>(<scope>): <subject>
```

Examples:

```
feat(backend/catalog): add attributes
fix(admin/ui): fix sidebar collapse on mobile
chore(storefront): bump next to 15.x
docs(compose): document dev port overrides
chore(monorepo): move xshop-backend into apps/backend
```

## `<type>` (mandatory)

| Type     | When to use                                            |
|----------|--------------------------------------------------------|
| `feat`   | New feature                                           |
| `fix`    | Bug fix                                               |
| `refactor`| Code change that neither fixes a bug nor adds a feature |
| `perf`   | Performance improvement                               |
| `docs`   | Documentation only                                    |
| `style`  | Formatting, whitespace, missing semicolons (no logic change) |
| `test`   | Adding/updating tests                                 |
| `build`  | Build system, CI, dependencies                        |
| `chore`  | Maintenance, tooling, moving/restructuring            |
| `ci`     | CI configuration / pipeline changes                   |
| `revert` | Revert a previous commit                              |

Pick the single most fitting type. If a commit mixes concerns, split it.

## `<scope>` (mandatory)

### App changes — `app/module`

Name the **app first, then the module/area** it belongs to.

```
backend/catalog
backend/order
backend/cart
admin/ui
admin/users
storefront/product
```

This project is organized around **DDD and modules**, so scope should reflect the module. Use as much granularity as is genuinely relevant — `backend/catalog` over a flat `backend` when the change is isolated to a module.

For cross-cutting changes inside one app (config, refactor, infrastructure), the app alone is enough:

```
feat(backend): add health-check middleware
refactor(storefront): extract shared api client
```

### Root / global changes

Use `monorepo` or a root-area scope for work that lives at the repo root (compose, env, docs, shared config). This is the one family of scopes that is inherently monorepo-only.

```
chore(monorepo): move xshop-backend into apps/backend
chore(deps): bump root dev dependencies
build(compose): parameterize backend host ports
docs(compose): document dev overrides
docs(monorepo): explain repo layout
```

### A rule of thumb

Use a scope that **remains meaningful if the app becomes its own repo**.

- `backend/catalog` → becomes `catalog` in a standalone backend repo.
- `chore(monorepo)` → used only for monorepo-specific work, which by definition won't survive a split.

## `<subject>` (mandatory)

- Imperative mood, present tense: "add", "fix", "remove" — not "adds", "fixed".
- Lowercase, no trailing period.
- Concise (< 50 chars is ideal).

```
feat(backend/catalog): add attributes        ✔
feat(backend/catalog): Adds attributes.      ✘
```
