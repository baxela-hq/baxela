<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` (resolved from this file's directory; in monorepos the `next` package may not be visible from the repo root) before writing any code. Heed deprecation notices.

This block is written and re-added by `next dev` — verify at `node_modules/next/dist/server/lib/generate-agent-files.js`. Removing it from a diff only re-creates the uncommitted change; committing it with your work keeps the tree clean.

<!-- END:nextjs-agent-rules -->

## i18n Conventions

- Locale files live in `messages/{locale}/` (currently `en` only; locales are an open set — `fa` will be added later as a mirrored folder). **next-intl** is the chosen library for future wiring; it is not installed yet and no component consumes these files yet.
- Namespace = folder/file path: `shared/common`, `shared/layout`, `home/home`, `catalog/products`, `catalog/product`, `checkout/checkout`, `auth/auth`.
- Interpolation uses ICU single-brace syntax (`{year}`), including plurals: `"{count, plural, one {# product} other {# products}}"`. Do not use i18next-style `{{var}}`.
- Sections group strings by function: `texts` (rendered copy), `labels` (field and aria labels), `actions` (buttons and controls), `placeholders`, `messages` (flow feedback owned by that section). Single-purpose leaves (e.g. `copyright.text`) and single-kind lists (e.g. `nav`) may stand flat.
- `shared/common.json` holds only generic, cross-page form scaffolding (`form.labels/actions/placeholders/validation`) and generic messages (`messages.error.general`, `messages.info.loading`). Flow-specific strings (newsletter, OTP, checkout, …) live in the feature file that owns the flow — feature files MAY define their own `messages`. This intentionally diverges from admin's "messages only in shared/common" rule.
- Catalog/content data does NOT belong in locale files — product names, category links, size values, review content, and shipping methods come from the backend API. So do page titles and SEO descriptions.
- Keys are snake_case. Every user-facing string in a page/component should have a key here; a key missing from one locale folder is a bug.
