# Baxela Storefront

The customer-facing storefront of the **Baxela** e-commerce platform — a
Next.js App Router application that consumes the Baxela Laravel API for
catalog browsing, product pages, cart, checkout and customer accounts.

Multilingual by default: English and Farsi (RTL) via `next-intl`, with locale
routing under `app/[locale]/`.

## Tech Stack

| Concern | Choice |
| --- | --- |
| Runtime | Next.js (App Router), React 19, TypeScript |
| Styling | Tailwind CSS v4 |
| i18n | next-intl (`en` / `fa`, RTL-ready) |
| API | fetch wrapper in `lib/api` (server + client helpers) |

## Getting Started

The storefront runs as part of the monorepo develop stack:

```bash
# from the repo root
cp .env.example .env
docker compose up -d
# → http://localhost:3000 (storefront), :5173 (admin), :8085 (API)
```

No host Node needed; the container installs dependencies on first start. To
run on the host instead:

```bash
pnpm install
cp .env.example .env.local   # then adjust the API URL
pnpm dev
```

### Environment Variables

| Variable | Description |
| --- | --- |
| `NEXT_PUBLIC_API_BASE_URL` | Browser-facing API base URL (e.g. `http://localhost:8085/api/v1`) |
| `SERVER_API_BASE_URL` | Optional override for server-side (SSR) fetches — used inside the compose networks to reach nginx directly |

## Commands

```bash
pnpm dev      # start the dev server (HMR)
pnpm build    # production build (next build)
pnpm start    # serve the production build
pnpm lint     # ESLint
pnpm preview  # build + run the Cloudflare Worker locally (demo branch)
pnpm deploy   # build + deploy to Cloudflare Workers (demo branch)
```

## Project Structure

```
app/[locale]/        # locale-scoped routes (shop pages, auth, account)
lib/api/             # fetch wrapper + server-side helpers, shared types
```

Full conventions — route organization, API layer patterns, i18n rules — are
documented in **[AGENTS.md](./AGENTS.md)**. Read it before contributing.

## Deployment

### Production (Docker, from `main`)

The production image (`infrastructure/docker/production/storefront/`) builds
the standalone server and runs `server.js` behind the port published by
`docker-compose.prod.yml`. `NEXT_PUBLIC_API_BASE_URL` is baked in at build
time; `SERVER_API_BASE_URL` is provided at runtime.

### Demo (Cloudflare Workers, from `demo/storefront-cloudflare`)

The demo branch deploys the SSR app to Cloudflare Workers via
[@opennextjs/cloudflare](https://opennext.js.org/cloudflare) — the build
transforms `next build` output into `.open-next/` (worker entry
`.open-next/worker.js` + static assets in `.open-next/assets`). This branch
drops `output: "standalone"` (Docker-only) and disables Next's image
optimizer; build the production Docker image from `main`.

```bash
pnpm install        # after switching to the branch
pnpm exec wrangler login   # once, with your Cloudflare account
NEXT_PUBLIC_API_BASE_URL=https://api-demo.baxela.com/api/v1 pnpm deploy
```

| Variable | Where it lives | Notes |
| --- | --- | --- |
| `SERVER_API_BASE_URL` | `wrangler.jsonc` → `vars` | Runtime value for server-side (SSR) fetches |
| `NEXT_PUBLIC_API_BASE_URL` | shell env when deploying | Inlined into client bundles at **build time** — must be set on every `pnpm deploy` |

The backend must allow CORS from the Workers demo domain for client-side
calls (cart, auth). `pnpm preview` runs the same Worker locally with
`wrangler dev`.
