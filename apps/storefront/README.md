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
pnpm build    # production build (.next/standalone — used by the prod Docker image)
pnpm start    # serve the production build
pnpm lint     # ESLint
```

## Project Structure

```
app/[locale]/        # locale-scoped routes (shop pages, auth, account)
lib/api/             # fetch wrapper + server-side helpers, shared types
```

Full conventions — route organization, API layer patterns, i18n rules — are
documented in **[AGENTS.md](./AGENTS.md)**. Read it before contributing.

## Deployment

The production image (`infrastructure/docker/production/storefront/`) builds
the standalone server and runs `server.js` behind the port published by
`docker-compose.prod.yml`. `NEXT_PUBLIC_API_BASE_URL` is baked in at build
time; `SERVER_API_BASE_URL` is provided at runtime.
