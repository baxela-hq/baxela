# Modules

The backend is a modular monolith (`nwidart/laravel-modules`). Every module lives under
`Modules/`, follows the same internal layout (Actions / Http / Models / Schemas /
Gateways / database / tests — see `AGENTS.md`), and is listed in
`modules_statuses.json`. Nineteen modules, all enabled:

| Module | Purpose |
|---|---|
| **Core** | Shared kernel: schema traits, `Money` (integer minor-unit math), exception mapping, ~60 domain-event contracts, gateway interfaces + DTOs, countries/currencies/languages seed data, idempotency + permission middleware |
| **Auth** | Sanctum auth, email-OTP sign-in, roles/permissions (spatie) with sync command, session management, admin user CRUD |
| **User** | Customer profiles, addresses, wishlist |
| **Catalog** | Products, variants, options/values, attributes (groups/values/templates), categories, brands, images, comments/reactions/rating aggregation, faceted public listing, CSV/JSON import-export, sitemaps |
| **Inventory** | Per-variant stock with restore-on-cancel listeners |
| **Cart** | Guest carts (token middleware) + user carts with merge-on-login, coupons, tax preview, checkout orchestrator (address → shipping → tax → inventory → discount → order in one transaction) |
| **Order** | Order aggregate with dual status tracks (fulfillment + payment, guarded state machine), order codes, expired-order cancellation, admin stats |
| **Payment** | 8 drivers — manual, Stripe, PayPal, Adyen, MercadoPago, Checkout.com, NowPayments, Razorpay — each with webhook signature verification; hosted-checkout flow |
| **Shipping** | Zones (+countries), rates, methods (+translations), user-side quotes, shipments with guarded transitions |
| **Tax** | Tax classes + rates, integer-exact math, tax snapshots on orders |
| **Discount** | Coupons with redemption guard, promotions scoped to products/categories |
| **Media** | Folder tree + media manager (local disk today; S3 planned — see repo `docs/ROADMAP.md`) |
| **Content** | CMS pages, blog posts/categories/comments, featured items, sitemaps |
| **Menu** | Nested menus + links with per-language translations and circular-move guard |
| **Notification** | 4 channels (email, database, web-push/VAPID, Telegram), per-recipient preference matrix, Reverb broadcasting |
| **Contact** | Contact-form messages + newsletter subscribers |
| **Support** | Customer/admin support tickets with threaded messages |
| **Setting** | Store settings (title, description, language, currency, announcement bar, storefront theme) |
| **Audit** | Audit-log listener + admin browser with filters |

Cross-module coupling goes **only** through `Core/app/Contracts/` (domain events +
gateway interfaces) — modules never touch each other's models or tables directly.
The per-module deep dive (tables, events, dependencies) is in [`v1/modules.md`](v1/modules.md).
