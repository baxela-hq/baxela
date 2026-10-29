# Baxela 

A developer-first, modular, headless e-commerce platform built with **Laravel**, **Next.js**, and **React**.

## ✨ Features
- Modular monolith (nwidart/laravel-modules)
- Event-Driven Architecture
- Headless (API-first)
- Shared-hosting friendly
- Flows documented (order, payment, promotions); Bruno API collection as the working API reference
- SOLID & Open/Closed compliant

## 🧱 Architecture
- Backend: Laravel (Modular Monolith)
- Storefront: Next.js
- Admin Panel: React (Vite)

## 📦 Modules
See `/docs/modules.md`

## 📚 Documentation
- `/docs/modules.md` — the 19 modules
- `/docs/flows` — order flow, order state machine, payment flow, promotion price resolution
- `/docs/api` — interactive OpenAPI docs (Scramble; local-only by default), JSON at
  `/docs/api.json`
- Bruno API collection at the repo root (`api/bruno/`)

## 🚀 Installation
```bash
git clone ...
cp .env.example .env         # repo root: compose variables (ports, DB creds)
docker compose up -d          # php-fpm + nginx + MySQL + Redis
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```
## ⚙️Configuration
Development runs from the repo root (`docker-compose.yml`).

**Media storage**: uploads go to the default filesystem disk (`public` locally). For S3,
set `FILESYSTEM_DISK=s3` plus the `AWS_*` variables (`AWS_ACCESS_KEY_ID`,
`AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL` if fronted by a
CDN) — each media row records its disk and URLs/deletes resolve against it. For production see
`docker-compose.prod.yml` and [`infrastructure/docker/production/README.md`](../../infrastructure/docker/production/README.md);
shared-hosting deploys are covered by `infrastructure/sharedhosting/`.

## 🧪 Testing
```bash
php artisan test
```

## 🛠 Extending the Platform

This platform is built to be extended via:
* Events & listeners
* Module overrides
* Custom modules

