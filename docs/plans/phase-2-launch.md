# Phase 2 Plan — Package & Public Launch (Week 4)

> Execution plan for Phase 2 of `docs/ROADMAP.md`. Prerequisite: Phase 1 exit criteria all
> met. This phase is mostly assets + marketing operations; only 2.1 and 2.2 touch the repo.
> Do not post anywhere before the tag exists (2.1).
>
> **Timing:** Tue–Thu launch morning Eastern time. Avoid late December. November or
> mid-January are the windows.
>
> **Exit criteria:** v0.1.0-alpha tagged with release notes · launch assets live in README
> · all channels posted · community channels open · same-day issue replies during week 1.

## Verified state (2026-10-21)

- Demos exist: `demo.baxela.com` + `admin-demo.baxela.com` (linked from root README);
  mobile app repo `github.com/baxela-hq/mobile-app`.
- Root README sections: Live Demo, Tech Stack, Structure, Quick start, API Collection
  (Bruno), Layout guidelines, Contributing, Moving apps.
- Competitive set for the comparison table (stars as of 2026-10-21): Bagisto 28.3k★ MIT ·
  BeikeShop 2k★ OSL-3.0 · InnoShop 664★ · Velstore 660★ MIT (stalled since May 2026).
- Community scaffolding status after Phase 0: root issue templates, CONTRIBUTING,
  SECURITY, CHANGELOG, CoC exist; Discussions + topics + social preview set in
  Phase 0 task 0.6 (verify, don't redo).

---

## 2.1 Tag v0.1.0-alpha

1. Freeze: all Phase 1 exit criteria ticked, CI green on `main` (incl. e2e job or its
   tracking issue), `composer analyse` at baseline target.
2. `CHANGELOG.md`: add `## [0.1.0-alpha] - YYYY-MM-DD` — highlights (multi-currency
   display, 6 locales/RTL, 8 payment drivers, e2e + 700+ backend tests, OpenAPI docs,
   S3 media), known limitations (SQL LIKE search, manual FX rates, no multi-vendor —
   stated plainly with roadmap pointers), install pointers.
3. `git tag -a v0.1.0-alpha -m "..."` → push tag → GitHub Release using the CHANGELOG
   entry + a 3-screenshot header (storefront, admin, checkout).
4. Dry-run the Release page as a stranger: does quickstart work from a clean clone?
   `git clone` → `cp .env.example .env` → `docker compose up -d` → store loads. Fix
   whatever blocks this before announcing anything.

Suggested commit: `chore(monorepo): release v0.1.0-alpha`

## 2.2 Launch assets (repo changes)

1. **Demo GIF** (60–90s, <10MB, muted, top of README): storyboard — storefront home (EN)
   → switch locale to FA (RTL flip) → switch currency → PDP → add to cart → checkout
   steps → admin dashboard → orders → payment methods grid. Record at 1280×720; loop
   seamlessly. Host in repo `docs/assets/` (git-LFS optional) or embed via release.
2. **Architecture diagram** (PNG in README): monorepo boxes (Laravel API — 19 modules,
   contracts/events core; React admin; Next storefront; mobile) + infra row (MySQL,
   Redis, Reverb, queue/scheduler, S3-optional, Mailpit-dev).
3. **Comparison table** (README section "How does Baxela compare?"): rows — stack,
   storefront (decoupled Next 16 / admin React 19), payment gateways (8 with webhook
   verification), multi-currency (display conversion + manual rates), i18n/RTL (6 locales
  incl. RTL-first), testing (700+ Pest + Playwright e2e + permission-audit test), API
   docs (OpenAPI + 417-request Bruno), deployment (compose prod + shared hosting),
   license (MIT). Columns: Baxela, Bagisto, BeikeShop, InnoShop, Velstore. Be factual —
   note "verify on their repos, data from Oct 2026"; honest tables travel further than
   flattering ones.
4. **Quickstart section rewrite**: 4 commands to a running store (compose up, migrate via
   the one-shot service, seed, open :3000/:5173); troubleshooting links (issues/
   discussions).
5. **Docs landing**: restructure `docs/README.md` as an index — Install (dev compose,
   prod compose, shared hosting), Configuration (env vars incl. AWS_*/FX), Flows
   (order/payment/promotions), API (link to /docs/api + Bruno), Roadmap & plans
   (ROADMAP.md + these phase plans), Third-party services gap inventory.

Suggested commit: `docs(monorepo): add launch assets and docs landing`

## 2.3 Community scaffolding completion

1. Label ~10 real `good-first-issue` items — include "add locale X" (per new locale,
   pointing to the Phase 1.4 mechanics), small docs fixes, first e2e edge cases.
2. Pin an intro issue: what Baxela is, GIF, demo links, what feedback is wanted most,
   roadmap link.
3. Discussions categories seeded with 2–3 starter threads (self-intro, "what should
   v0.2 prioritize?", showcase thread).

## 2.4 Launch sequence (the week of the tag)

Post in this order, same week; personalize per channel, never paste one blob:

1. **Laravel News** (https://laravel-news.com/ contribute form) — a Baxela intro post;
   their acceptance usually lands a newsletter slot. Give them the GIF + quickstart.
2. **Show HN** — Tue/Wed/Thu 08:00–10:00 ET. Title: `Show HN: Baxela – Headless,
   multi-gateway e-commerce platform (Laravel, Next.js, React)`. First comment by you:
   why it exists (GitLab → GitHub solo journey, ports-and-adapters, 8 gateways), demo
   links, what's deliberately missing (search, multi-vendor), what feedback you want.
   Rules: answer every question hour 1–4; no vote-asking anywhere.
3. **Reddit** — r/laravel, r/PHP (text posts; check each subreddit's self-promo rules
   first, some require ≥10% participation or a modmail), r/ecommerce (angle: evaluating
   platforms, comparison table).
4. **X thread** (8–10 posts): the build story → architecture diagram → RTL/locale demo →
   payment gateways list → e2e test running → honest gaps + roadmap → demo links + star
   CTA. Pin to profile.
5. **dev.to article** — "Why I built my own e-commerce platform" (the honest engineering
   narrative: contracts layer, idempotent checkout, order state machine; what went
   wrong; GitLab→GitHub migration). Cross-link HN discussion later.
6. **awesome lists** — PRs: awesome-laravel, awesome-php, awesome-nextjs (storefront
   angle), awesome-selfhosted (if the deploy story fits their criteria — read them first;
   self-hosted platforms usually qualify).
7. **Persian dev communities** — Telegram groups/Discord (e.g., Laravel Iran community
   channels): FA demo + RTL story; this channel is underserved and high-yield.

## 2.5 Launch-day runbook

- [ ] Everything posted before 10:00 ET? Track each channel's URL in this file
- [ ] Demo sites up (hit them once pre-post; watch error rates — Sentry dashboards exist
      in all three apps)
- [ ] Reply SLA < 2h for the first 72h; triage: bugs → issues with repro, feature asks →
      Discussions, hostile takes → one calm factual reply, move on
- [ ] Watch star graph (star-history.com embed) + referrers (GitHub traffic insights) —
      note which channel converts; it decides where Phase 3 content goes
- [ ] If any checkout-breaking bug appears: fix forward, patch release `v0.1.0-alpha.1`,
      reply in-thread with the fix commit

## Definition of done

- [ ] Tag + Release live, quickstart verified from clean clone
- [ ] README carries GIF, diagram, comparison table, rewritten quickstart
- [ ] All channels posted; URLs recorded below
  - Laravel News: ___ · HN: ___ · Reddit: ___ · X: ___ · dev.to: ___ · awesome PRs: ___
- [ ] 10 good-first-issues labeled; intro issue pinned
- [ ] Launch-week star count recorded: ___ (target ~100)
