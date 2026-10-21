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
- Bruno API collection at the repo root (`api/bruno/`) — the de-facto API reference

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
Development runs from the repo root (`docker-compose.yml`). For production see
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

