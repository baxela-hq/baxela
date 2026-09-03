<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->

## i18n Conventions

- **next-intl is installed and wired.** Locales: `en` (default, `/` → `/en`) and `fa` (RTL). Every locale gets a path prefix (`localePrefix: 'always'`).
- Routing/config lives in `i18n/` — `routing.ts` (locale list), `request.ts` (loads + merges the dictionary files per locale), `navigation.ts` (locale-aware `Link`/`useRouter`/`usePathname`/`redirect` — import from here, never from `next/link`/`next/navigation`), plus root `proxy.ts` (Next 16's renamed middleware). All pages live under `app/[locale]/`; commerce pages sit in the `(shop)` group (shared `SiteHeader`/`SiteFooter`), auth pages sit directly under `[locale]` (full-screen `AuthShell`).
- Locale files live in `messages/{locale}/` (`en` + `fa`, open set — a new locale = mirrored folder + `routing.ts` entry). Runtime namespaces mirror file paths with dots: `messages/en/shared/common.json` → `useTranslations('shared.common')`.
- Interpolation uses ICU single-brace syntax (`{year}`), including plurals: `"{count, plural, one {# product} other {# products}}"`. Do not use i18next-style `{{var}}`.
- Sections group strings by function: `texts` (rendered copy), `labels` (field and aria labels), `actions` (buttons and controls), `placeholders`, `messages` (flow feedback owned by that section). Single-purpose leaves (e.g. `copyright.text`) and single-kind lists (e.g. `nav`) may stand flat.
- `shared/common.json` holds only generic, cross-page form scaffolding (`form.labels/actions/placeholders/validation`) and generic messages (`messages.error.general`, `messages.info.loading`, `messages.info.not_found`). Flow-specific strings (newsletter, OTP, checkout, …) live in the feature file that owns the flow — feature files MAY define their own `messages`. This intentionally diverges from admin's "messages only in shared/common" rule.
- Catalog/content data does NOT belong in locale files — product names, category links, size values, review content, and shipping methods come from the backend API. So do page titles and SEO descriptions.
- Keys are snake_case. Every user-facing string in a page/component should have a key here; a key missing from one locale folder is a bug.
- **RTL rules (fa)**: `<html dir>` is set from the locale in `app/[locale]/layout.tsx`. Use logical CSS utilities (`ms-`/`me-`/`ps-`/`pe-`/`start-`/`end-`/`text-start`/`text-end`) — never physical `left/right`/`pl-`/`pr-` — except pure centering (`left-1/2 -translate-x-1/2`, direction-neutral). Off-canvas slides need explicit `ltr:`/`rtl:` variants. Headings with `uppercase tracking-*` must pair with `rtl:normal-case rtl:tracking-normal` (letter-spacing breaks Arabic-script joining). Persian uses the Vazirmatn font (`--font-vazirmatn`, swapped in `globals.css` under `html[dir="rtl"]`); Geist has no Arabic-script glyphs. Format numbers/currency with next-intl's formatter so digits localize.
