# Baxela 

A developer-first, modular, headless e-commerce platform built with **Laravel**, **Next.js**, and **React**.

## ✨ Features
- Modular monolith (nwidart/laravel-modules)
- Event-Driven Architecture
- Headless (API-first)
- Shared-hosting friendly
- Fully documented (API, flows, ERD)
- SOLID & Open/Closed compliant

## 🧱 Architecture
- Backend: Laravel (Modular Monolith)
- Storefront: Next.js
- Admin Panel: React (Vite)

## 📦 Modules
See `/docs/modules.md`

## 📚 Documentation
- `/docs/architecture.md`
- `/docs/flows`
- `/docs/database`
- `/docs/api`

## 🚀 Installation
```bash
git clone ...
docker compose up -d
composer install
php artisan migrate
```
## ⚙️Configuration
See `/docs/deployment`

## 🧪 Testing
```bash
php artisan test
```

## 🛠 Extending the Platform

This platform is built to be extended via:
* Events & listeners
* Module overrides
* Custom modules

