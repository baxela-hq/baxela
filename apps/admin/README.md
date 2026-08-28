# xShop Admin

The admin panel of the **xShop** e-commerce platform — a React SPA that consumes the xShop
Laravel API to manage catalog, content, media, orders, users and system settings.

Built on top of [shadcn-admin](https://github.com/satnaing/shadcn-admin) (see
[Credits](#credits)). Multilingual and RTL-ready by design: the UI ships Farsi (`fa`) and
English (`en`) today, and content entities carry per-language translations driven by the
backend language list — more locales can be added without structural changes.

## Features

- **Catalog** — products (with variants, options & images), categories, product options and option values
- **Content** — CMS pages with rich-text editing (Tiptap)
- **Media library** — folders, uploads and a reusable media picker
- **Orders** — order management with status updates
- **Users & Settings** — admin users, system settings
- Server-driven data tables (pagination, sorting, `filter[field]` filters — all synced to the URL)
- Light/dark mode, layout variants, font options, LTR/RTL toggle
- Responsive, accessible, global search (Ctrl/Cmd+K)

## Tech Stack

| Concern | Choice |
| --- | --- |
| Runtime | React 19, Vite 7 (SWC), TypeScript (strict) |
| Routing | TanStack Router (file-based) |
| Server state | TanStack Query |
| Tables | TanStack Table (server-side pagination/sorting/filtering) |
| UI | Tailwind CSS v4, shadcn/ui (new-york), Radix UI |
| Forms | react-hook-form + zod |
| HTTP | axios |
| i18n | i18next (`public/locales/{lang}/*.json`) |
| State | zustand (auth) + React context (theme/dir/font/layout) |

## Prerequisites

- **Node.js** ≥ 20.19 (or ≥ 22.12) — required by Vite 7
- **pnpm** (`corepack enable` or `npm i -g pnpm`)
- A running **xShop backend** (Laravel API) the panel can talk to

## Getting Started

```bash
# 1. install dependencies
pnpm install

# 2. configure environment
cp .env.example .env
#    then edit .env (see table below)

# 3. start the dev server
pnpm dev
```

### Environment Variables

| Variable | Description |
| --- | --- |
| `VITE_API_BASE_URL` | Base URL of the xShop Laravel API (e.g. `http://xshop-backend.local/api/v1`) |
| `VITE_STORE_FRONT_URL` | Storefront base URL, used for product/page preview links |
| `VITE_CLERK_PUBLISHABLE_KEY` | Optional — only for the `/clerk/*` demo tree; leave empty otherwise |

## Commands

```bash
pnpm dev             # start Vite dev server (HMR)
pnpm build           # TYPE-CHECK (tsc -b) + production build → dist/
pnpm preview         # preview the production build locally
```

### Code Quality

```bash
pnpm lint            # ESLint — catches syntax/lint errors and bad practices
pnpm lint --fix      # ESLint with auto-fix (unused imports, type imports, etc.)

pnpm format          # Prettier — rewrite all files with the project style
pnpm format:check    # Prettier — check only, no writes

pnpm build           # full TypeScript type-check + build (fastest way to catch type errors)
npx tsc -b           # type-check only, without emitting/bundling

pnpm knip            # detect unused files, exports and dependencies
```

`no-console` is an ESLint **error** in this repo, and the build fails on unused
variables/imports (`noUnusedLocals`/`noUnusedParameters`) — so run `pnpm lint` and
`pnpm build` before committing. There is no test suite yet.

## Project Structure

```
src/
├── routes/              # TanStack Router file tree (thin wrappers around features)
├── features/            # all page code, grouped by domain (catalog, content, media, …)
│   └── <domain>/<entity>/{api,components,data}/
├── components/          # ui primitives, shared data-table parts, layout, tiptap
├── shared/              # API client, error types, locale/tree utils, shared types
├── hooks/               # useAppTranslation, useTableUrlState, useDialogState, …
├── stores/              # zustand auth store
├── context/             # theme / direction / font / layout providers
├── i18n/                # i18next setup (fa default, en fallback)
└── styles/              # Tailwind CSS v4 theme
public/locales/{lang}/   # translation files per locale
```

Full conventions — feature-module anatomy, API layer patterns, naming rules, i18n
guidelines and known gotchas — are documented in **[AGENTS.md](./AGENTS.md)**. Read it
before contributing (humans and AI agents alike).

## Deployment

Any static host works (the app is a pure SPA with `/* → /index.html` fallback — see
`netlify.toml`). Build with `pnpm build` and serve `dist/`, providing the env vars at
build time.

## Credits

This project is a customized fork of
[**shadcn-admin**](https://github.com/satnaing/shadcn-admin) by
[@satnaing](https://github.com/satnaing) — an admin dashboard UI crafted with ShadcnUI,
built with responsiveness, accessibility and RTL support in mind. Some `src/components/ui`
components retain RTL customizations from the original.

The optional `/clerk/*` demo tree is sponsored by [Clerk](https://go.clerk.com/GttUAaK).

## License

Licensed under the [MIT License](./LICENSE) © 2024 Sat Naing.
