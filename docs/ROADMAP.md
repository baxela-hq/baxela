# Baxela Roadmap — Public Launch (v0.1.0-alpha) → 1,000 GitHub Stars

> **Purpose of this document:** Self-contained execution plan. Hand this file to an agent
> session (or work through it yourself) to review and implement, phase by phase.
> It contains all verified context needed — do not assume conversation history.
>
> **Created:** 2026-10-21 · **Owner:** solo maintainer · **Status:** awaiting execution
>
> **How to use:** Detailed, fact-checked execution plans for each phase live in
> `docs/plans/phase-{0..3}.md` — prefer those; this file is the master outline. Work phases
> strictly in order (0 → 1 → 2 → 3). Within a phase, task order
> is flexible unless a task says otherwise. Re-verify any fact below against the codebase
> before relying on it — numbers may have drifted since this was written. Tick the checkbox
> and note the date when a task completes. Repo conventions: read root `AGENTS.md` and each
> app's `AGENTS.md` first; conventional commits (`type(scope): subject`); never commit unless
> explicitly asked.
>
> **Open assumptions:** root license MIT; new-locale strings agent-drafted then
> owner-reviewed (both are flagged where they matter in the phase plans).

---

## Context — verified state as of 2026-10-21

**What Baxela is:** E-commerce platform monorepo — Laravel 13 modular monolith API
(`apps/backend`, 19 modules), React 19 + Vite admin (`apps/admin`, forked from shadcn-admin
v2.2.1), Next.js 16 storefront (`apps/storefront`), Bruno API collection (`api/`, 417
requests). Project predates its GitHub history (migrated from GitLab; GitHub monorepo
history starts 2026-08-28, ~514 commits since). Mobile app lives in a sibling repo. Live
demo sites exist.

**Strengths (keep, protect, market):**

- Ports-and-adapters core: `apps/backend/Modules/Core/app/Contracts/` holds ~60 domain
  events and gateway interfaces (Catalog, Discount, Inventory, Media, Order, Payment,
  Shipping, Tax, User) with DTOs — modules stay decoupled.
- 8 payment drivers with per-gateway webhook signature verification and tests: Stripe,
  PayPal, Adyen, MercadoPago, Checkout.com, Razorpay, NowPayments (crypto), Manual
  (`Modules/Payment/config/payment.php`). Payment is the best-tested module (21 test files).
- ~726 backend tests across 123 Pest files; CI runs commit-lint, i18n-parity check
  (`scripts/check-i18n-parity.mjs`), backend Pest, admin + storefront builds.
- Idempotent checkout orchestrator (address → shipping quote → tax → inventory → discount
  → order, in one DB transaction); guarded dual-track order state machine (documented in
  `apps/backend/docs/flows/order-state-machine.md`).
- 4-channel notifications (Email, Database, WebPush/VAPID, Telegram) over Reverb realtime;
  RBAC via spatie permissions with sync command; audit log; CSV/JSON catalog import-export.
- Bilingual en/fa including full RTL storefront — no competitor on the GitHub
  `php-ecommerce` topic serves this.
- Real deployment story: dev compose, prod compose (multi-stage Dockerfiles, one-shot
  migrate, queue, scheduler, Reverb), plus an unusual **shared-hosting** path
  (`infrastructure/sharedhosting/`) that competitors explicitly refuse.

**Gaps (what this plan fixes, in priority order):**

1. **No root LICENSE** — nobody can legally use Baxela today. Highest-priority item in the plan.
2. **Not packaged as a project**: no git tags, no releases, no platform CHANGELOG, README
   overclaims (Scramble API docs "implemented" but unwired) and links to docs that don't
   exist (`/docs/architecture.md`, `/docs/api`, `/docs/deployment`, `/docs/database`).
3. **No visitor-facing multi-currency** — see dedicated section below (confirmed correct and in scope).
4. **Test gaps** (verified 2026-10-21): Shipping's 19 admin controllers and Menu's
   update/delete/permission paths are untested (Support, Audit, and Setting turned out
   adequately covered despite low file counts); zero frontend/e2e tests (no framework
   installed); phpstan baseline suppresses ~3,200 errors (`apps/backend/phpstan-baseline.neon`
   — regenerate for the live count).
5. **Search is SQL `LIKE` only**; **media is local-disk only** (S3 enum exists, flysystem-aws
   adapter not installed); locales limited to en/fa.
6. **Template/mock leftovers** in admin and storefront (detailed in Phase 1).

**Competitive landscape (GitHub topic `php-ecommerce`, checked 2026-10-21):** Bagisto 28.3k★
(ecosystem king — multi-vendor, marketplace, don't fight it head-on), BeikeShop 2k★ (OSL-3.0,
plugin marketplace, native multi-currency), InnoShop 664★ (Laravel 13, AI/MCP positioning,
young), Velstore 660★ (multi-vendor, 19 translations, stalled since May 2026). Everything
else on the topic is hobby/student code. Positioning for Baxela: **developer-first, headless
(decoupled API + admin + storefront + mobile), multi-gateway, truly bilingual/RTL** — not a
feature-count war with Bagisto.

**Goals:**

1. Tag and publish **v0.1.0-alpha.1** publicly (~3–4 weeks of work at current velocity;
   the full stage plan — alpha → beta → 1.0 gates — lives in `docs/RELEASE_LIFECYCLE.md`).
2. Execute a coordinated public launch (Show HN, Laravel News, Reddit, X, Persian dev
   communities).
3. Reach **1,000 GitHub stars within 6–9 months** via release cadence + content + community.

**Explicit non-goals for v0.1.0-alpha** (defer, list publicly as roadmap): multi-vendor /
marketplace, Meilisearch integration, external FX rate APIs, SMS/social login, newsletter
ESP integration, Iranian payment gateway.

---

## The multi-currency situation (verified — do not re-litigate)

**What exists today (correct, keep):** Baxela fully supports a **configurable store
currency**. `Modules/Core/app/Models/Currency.php` is seeded from
`Modules/Core/database/data/currencies.php`; the store default is the `currency_id` setting
(`Modules/Setting/app/Schemas/Setting/SettingNameEnum.php`); the storefront formats every
price from it (`apps/storefront/lib/currency.ts` — symbol, symbol side, decimal places).
Running the entire store in USD, EUR, or IRR works today.

**What's missing (confirmed gap):** visitor-facing multi-currency. There is no currency
switcher, no exchange-rate storage, no display conversion, and the only public currency
endpoint is `Modules/Core/app/Actions/Public/Currency/ShowDefaultCurrencyAction.php`. The
gap is already acknowledged in-repo at `docs/THIRD_PARTY_SERVICES.md:144`:
*"Multi-currency model has no FX rate source."* BeikeShop, InnoShop, and Bagisto all
advertise native multi-currency, so this is a visible row in any feature comparison.

**v0.1 implementation (task 1.7 below):** display-currency conversion, not per-currency
price lists.

- Admin: manage currencies (already has list) + an editable exchange-rate field against the
  base currency. Manual rates only for v0.1 — no external API (exchangerate.host / Open
  Exchange Rates are documented future upgrades).
- Storefront: currency switcher (guest-friendly, persisted cookie/localStorage); prices,
  cart, and totals converted for display using the Core Money utility's integer arithmetic
  and sane per-currency rounding rules.
- Charging stays in the **base currency**; orders snapshot the applied rate + display
  currency alongside the existing tax snapshot in the Order module.
- Public API: extend the currency endpoint(s) to expose active currencies + rates.

---

## Phase 0 — Legal & truth (Week 1, ~2–3 days total)

Goal: make the repo legally usable and every public claim true. Nothing here is optional.

- [ ] **0.1 Root LICENSE.** Add MIT at repo root (**decision already leaning MIT** — max
  adoption, matches Bagisto/Velstore, compatible with `apps/admin`'s upstream shadcn-admin
  MIT license which must remain in place). BLOCKER: without this, no one may legally use
  Baxela. *Owner decision required if MIT is rejected (GPL-3.0 is the alternative).*
- [ ] **0.2 README truth pass.** In `apps/backend/README.md` and root `README.md`: remove or
  implement the Scramble API-docs claim; delete links to non-existent `/docs/architecture.md`,
  `/docs/api`, `/docs/deployment`, `/docs/database`; verify every remaining link resolves.
- [ ] **0.3 Rewrite `docs/modules.md`** (backend) to describe the real 19 modules instead of
  the aspirational structure (it currently mentions modules like "Identity" and "Pricing"
  that don't exist).
- [ ] **0.4 Secrets audit.** Grep repo for Sentry DSNs, API keys, tokens, passwords;
  confirm `.env.example` files contain placeholders only; confirm public demo databases
  hold no real customer data.
- [ ] **0.5 SECURITY.md** at repo root + enable GitHub private vulnerability reporting on
  the repo.
- [ ] **0.6 Repo polish.** GitHub description + homepage (demo link); topics: `php-ecommerce`,
  `laravel`, `ecommerce`, `nextjs`, `react`, `headless-commerce`, `storefront`, `rtl`,
  `multi-vendor` (only when true); 1200×640 social preview image; empty `CHANGELOG.md`
  (Keep a Changelog format) awaiting v0.1.0-alpha entry.

**Phase 0 exit criteria:** repo is legally usable, README/docs contain zero false claims,
no secrets in-tree, repo page looks intentional.

---

## Phase 1 — v0.1.0-alpha hardening (Weeks 1–3, parallelizable)

Goal: nothing embarrassing or unsafe on launch day. Work through in rough priority order.

### Security

- [ ] **1.1 Rate limiting.** Throttle OTP request/verify, login, password-reset, and
  contact/newsletter endpoints; add lockout on repeated OTP failure. Verify email OTP
  flow can't be brute-forced (codes expire, attempts capped).
- [ ] **1.2 Admin route audit.** Confirm every route under `api/v1/{module}/admin/...`
  sits behind `Core\Http\Middleware\PermissionMiddleware` + `auth:sanctum`; fix any gaps;
  add a test that enumerates admin routes and asserts protection.

### Tests (worst gaps first)

- [ ] **1.3 Shipping test backfill.** 22 controllers exist, 2 test files today
  (`Modules/Shipping/tests/`). Target: zone/rate/method CRUD, quote calculation, shipment
  transitions — parity with Cart/Order module coverage.
- [ ] **1.4 Thin-module tests.** Menu, Support, Setting, Audit (1 file each), Media,
  User, Inventory, Contact (2–3 each). At minimum: happy path + one auth/permission test
  per module's admin surface.
- [ ] **1.5 Shrink the phpstan baseline.** `apps/backend/phpstan-baseline.neon` currently
  suppresses ~3,200 errors (3,202 on 2026-10-21 — regenerate first for the live count).
  Work module-by-module (Core → Catalog → Payment → rest); target
  **< 500** by alpha tag, fix-not-suppress. Track the number in the CHANGELOG entry.
- [ ] **1.6 E2E happy path (Playwright).** Install Playwright in `apps/storefront` (first
  frontend test framework in the monorepo). One test: home → PDP → add to cart → checkout
  (address → shipping quote → manual payment) → order created → visible in profile orders.
  Add CI job (separate from build, allowed to fail-fast). The checkout page is ~800 lines
  and the product's crown jewel — it must not break publicly.

### Features in scope for v0.1

- [ ] **1.7 Multi-currency (display conversion).** Implement exactly per the dedicated
  section above: admin-managed manual FX rates, storefront switcher, Money-utility integer
  conversion with rounding, order snapshot of applied rate, extended public currency API.
  Add Pest tests for rate resolution + conversion + snapshotting; add store switcher states
  to the Playwright happy path.
- [ ] **1.8 Locale expansion — all three apps, backend first.** Ship en, fa + **de, fr, es,
  ar** (Arabic proves RTL isn't a one-off).
  - **Backend (highest impact — customer-facing email/Telegram templates live here):**
    add `Modules/*/lang/{de,fr,es,ar}/` mirroring each module's `en/` tree (18 modules:
    `errors.php` everywhere; `auth.php`, `address.php`; `seeder.php` for
    Catalog/Content/Menu/Setting/Shipping; Notification also carries `notifications.php`
    + `telegram.php`). Fallback reality drives the order: `errors.php` misses degrade
    gracefully to English via Laravel's fallback locale (progressive delivery is safe),
    but `LocaleTemplateEngine` keys notification templates by locale inside one file
    (`notifications.$locale.…`) and renders the **raw translation key** as the subject
    when a locale is absent — so first harden `LocaleTemplateEngine` to fall back to
    `en` (small patch), then translate Notification's files fully per locale.
  - **Admin + storefront:** mirror `apps/admin/public/locales/{de,fr,es,ar}/` namespaces
    and storefront `messages/{de,fr,es,ar}/`.
  - Extend `scripts/check-i18n-parity.mjs` to also walk `apps/backend/Modules/*/lang/`
    (today it covers only the two frontends); keep CI green. After launch, new locales
    move to community PRs (see 3.4).
- [ ] **1.9 Finish S3 media driver.** Install flysystem-aws adapter; implement the existing
  `S3` disk enum path; admin setting to pick disk; docs section for both local and S3.

### Cleanup

- [ ] **1.10 Admin template leftovers (list corrected 2026-10-21).** Remove `/sign-in-2`,
  `/sign-up`, `/forgot-password` (mock `console.log` forms), the template
  `/settings/display` + `/settings/appearance` pages, and the faker-seeded template
  `src/features/users/` + `/users` route (the real users feature is `features/user/users`).
  **Keep `/catalog/attributes/groups`, `/templates`, `/values/$id` — they are live Baxela
  attribute features, not leftovers** (this task's original list was wrong about them).
  Also delete the orphaned storefront `coming-soon-panel.tsx` and the mock saved-cards
  page. Exact steps + link-site fixes: `docs/plans/phase-1-alpha-hardening.md` §1.8.
- [ ] **1.11 Storefront mock/placeholder removal.** Hide mock saved-cards UI behind a
  feature flag or remove (`apps/storefront/components/account/saved-cards.tsx` states cards
  live in memory only); delete unused `coming-soon-panel.tsx` or wire it to unfinished pages.
- [ ] **1.12 Wire Scramble.** `dedoc/scramble` is installed but unwired. Register it,
  verify OpenAPI generation across the 325 API routes, link published docs from README.
  If wiring proves heavy, cut scope to public+user routes for v0.1 — but do not ship the
  claim without the feature.

**Phase 1 exit criteria:** security pass done, Shipping/thin modules tested, baseline < 500,
e2e green in CI, multi-currency + new locales demoable, no template leftovers, Scramble
claim true. Then tag.

---

## Phase 2 — Package & launch (Week 4)

Goal: one coordinated launch moment. Do not leak the announcement before the tag.

- [ ] **2.1 Tag `v0.1.0-alpha.1`** + GitHub Release (marked pre-release) with notes
  (highlights, known limitations
  — LIKE search, manual FX rates, no multi-vendor — stated honestly; upgrade path).
- [ ] **2.2 Launch assets.** 60–90s demo GIF (README top), architecture diagram (monorepo
  + contracts/events layer), honest comparison table vs Bagisto/BeikeShop/InnoShop/Velstore,
  `docker compose up -d`-to-store quickstart section, screenshots of admin + storefront +
  mobile app link.
- [ ] **2.3 Docs landing.** Index `docs/` + backend `docs/` into a navigable README-linked
  structure (VitePress site optional, defer if it slips the date): install (Docker, prod
  compose, shared hosting), configuration, FAQ.
- [ ] **2.4 Community scaffolding.** GitHub Discussions enabled; bug/feature issue
  templates; `CONTRIBUTING.md` (setup, conventions, commit hook install
  `sh scripts/install-hooks.sh`); label ~10 real issues `good-first-issue` (include locale
  translations); code of conduct.
- [ ] **2.5 Launch sequence** (in order, within the same week):
  1. Laravel News submission (laravel-news.com).
  2. Show HN, Tue–Thu morning Eastern — angle: "Show HN: I built a headless, multi-gateway
     e-commerce platform (Laravel + Next.js + React)".
  3. r/laravel, r/PHP, r/ecommerce posts (rule-compliant, no spam).
  4. X thread + dev.to build-in-public article (the GitLab→GitHub migration, solo-dev,
     ports-and-adapters story).
  5. PRs to awesome-laravel / awesome-php lists.
  6. Persian dev Telegram/Discord communities — underserved channel, Baxela's RTL + fa
     locale is the hook.
- [ ] **2.6 Launch-day ops.** Pinned intro issue; answer every comment/issue within hours;
  demo sites confirmed up; watch star graph (star-history.com) to learn which channel converts.

**Phase 2 exit criteria:** tagged release public, all channels posted, community channels
open, first issues being answered same-day.

---

## Phase 3 — Compound to 1,000 stars (Months 2–6)

Goal: convert launch spike into a compounding curve. Cadence beats bursts.

- [ ] **3.1 Release cadence every 2–3 weeks.** Tagged releases with notes + a GIF each.
  This is the single highest-leverage habit; the Releases tab is how late discoverers
  trust the project is alive.
- [ ] **3.2 Meilisearch integration** (v0.2 or v0.3). Catalog indexing, storefront search
  UI, admin reindex command, optional compose profile. Ship with a blog post — most
  visible feature gap today.
- [ ] **3.3 Engineering content.** 2–3 posts on the machinery: idempotent checkout, the
  dual-track order state machine, ports-and-adapters across 19 Laravel modules. These
  rank in search and convert readers better than feature announcements.
- [ ] **3.4 Community locales.** Move new translations to PR-driven (each locale = a
  contributor + their network); credit translators in release notes.
- [ ] **3.5 Multi-vendor scope decision** (~v0.3–v0.4). The one table row every real
  competitor has and Baxela doesn't. Even a minimal seller + commission flow changes the
  comparison story. Decide scope honestly; don't rush a half-system.
- [ ] **3.6 Showcase page** of real stores running Baxela.

**Star milestones:** ~100 launch week · ~300 by month 2 · ~600 by month 4 ·
**1,000 by month 6–9**. If Show HN fronts, the curve compresses — that's why Phase 2
assets matter as much as Phase 1 code.

**Launch window guidance:** November or mid-January; never the late-December dead weeks.

---

## Appendix A — Module test-file counts (baseline, 2026-10-21)

| Module | Test files | Notes |
|---|---|---|
| Payment | 21 | best-tested; per-gateway webhook tests |
| Catalog | 18 | largest module (395 files, 80 actions) |
| Auth | 16 | includes permission-matrix tests |
| Notification | 11 | 4 channels, 42 listeners |
| Content | 10 | CMS + blog + comments |
| Cart | 9 | incl. checkout orchestrator |
| Order | 9 | state-machine guards |
| Discount | 6 | coupons + promotions |
| Core | 6 | Money, DTOs, seed data |
| User | 3 | profiles, addresses, wishlist |
| Media | 3 | local disk only |
| Shipping | **2** | 19 admin + 2 user controllers — worst gap |
| Contact | 2 | no ESP |
| Inventory | 2 | single stock per variant |
| Support | **1** | tickets |
| Menu | **1** | nested menus |
| Setting | **1** | ~8 settings |
| Audit | **1** | log + list |

Totals: 123 Pest files, ~726 test functions. File count ≠ coverage — verified 2026-10-21:
Support's single file holds 9 thorough tests, Audit 6, Setting 5; the true gaps are
Shipping's admin surface and Menu's update/delete/permission paths. Frontend: **no test
framework installed in either admin or storefront** (Playwright arrives with Phase 1 —
`docs/plans/phase-1-alpha-hardening.md` §1.2).

## Appendix B — Key file references

| Topic | Path |
|---|---|
| phpstan baseline (3,076) | `apps/backend/phpstan-baseline.neon` |
| Currency model / seed | `apps/backend/Modules/Core/app/Models/Currency.php`, `Modules/Core/database/data/currencies.php` |
| Store currency setting | `apps/backend/Modules/Setting/app/Schemas/Setting/SettingNameEnum.php` (`CURRENCY_ID`) |
| Public currency endpoint (only one) | `apps/backend/Modules/Core/app/Actions/Public/Currency/ShowDefaultCurrencyAction.php` |
| Storefront price formatting | `apps/storefront/lib/currency.ts` |
| Multi-currency gap acknowledgment | `docs/THIRD_PARTY_SERVICES.md:144` |
| Order state machine docs | `apps/backend/docs/flows/order-state-machine.md` |
| i18n parity check | `scripts/check-i18n-parity.mjs` |
| CI | `.github/workflows/ci.yml` |
| Shared-hosting deploy | `infrastructure/sharedhosting/deploy.sh` |
| Mock saved cards | `apps/storefront/components/account/saved-cards.tsx` |
| Admin upstream license (keep) | `apps/admin/LICENSE` (shadcn-admin, MIT) |
