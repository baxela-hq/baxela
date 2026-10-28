# Launch Readiness Plan — close every brutal point, ship v0.1.0-alpha.1

> Created 2026-10-24. This is the single execution plan that finishes Phase 1 and closes
> every point from the 2026-10-24 re-assessment (the "brutal list"). Review first, then
> execute top to bottom — parts A/B are hours, C–E are day-sized, F/G end at launch.
> Companion docs: `docs/ROADMAP.md` (master), `docs/plans/phase-1-alpha-hardening.md`
> (§1.5–1.8 details, still authoritative), `docs/plans/phase-2-launch.md` (launch day),
> `docs/RELEASE_LIFECYCLE.md` (stage gates).
>
> **Already done — do NOT redo:** MIT license + governance, truthful docs, gitleaks CI,
> admin-route audit test, Playwright e2e passing, multi-currency end-to-end (switcher,
> admin UI, snapshots, EUR seeded), 6 locales (en/fa/de/fr/es/ar) in all three apps with
> parity green, returns/refunds system (existing), release-lifecycle doc.

## Brutal-point map

| # | Point | Closed by |
|---|---|---|
| 6 | CI workflows unverified | Part A |
| 4 | Embarrassing pages | Part B |
| 3 | phpstan baseline growing (3,240) | Part C |
| — | Scramble claim / S3 / Shipping+Menu tests (Phase 1 leftovers) | Part D |
| — | FX rates manual-only (your new feature fixes this) | Part E |
| 2 | Multi-currency overclaim risk | Part F |
| 5 | Returns/refunds unmarketed | Part F |
| 1 | Adoption = 0 | Part G (launch) |
| 7 | Bus factor = 1 | Part G (contributor pipeline) — ongoing after launch |

---

## Part A — Verify and unblock CI (point 6) — ~30 minutes

1. DONE 2026-10-24 — outcomes: **E2E green 4/4 since its first run** (compose boots and
   the happy path passes in CI). **CI red on every run since Oct 18** — three independent
   causes, all fixed same day: (a) `MissingAppKeyException` — `phpunit.xml` set no
   `APP_KEY`, local `.env` masked it, env-less CI died (test key added);
   (b) `PostTest` scheduling assertion raced the second boundary on slow runners
   (`freezeTime()` added); (c) admin `pnpm install --frozen-lockfile` failed under pnpm 11
   (build-script approvals + lockfile settings — `pnpm-workspace.yaml` `allowBuilds:`
   added, lockfile regenerated with pnpm 11.21.0).
2. DONE — gitleaks-action failed for orgs without a license, not findings: the job now
   runs the pinned gitleaks binary directly. The Sentry fixture never tripped.
3. DONE — E2E was green from run one; `continue-on-error` removed 2026-10-24.
4. DONE 2026-10-24 — 549 commits scanned: **4 findings, all deliberate test fixtures**
   (an Adyen-shaped API-key string in 3 Payment test files, a personal string as an
   idempotency key in `Checkout.bru`). Fixtures replaced with obvious placeholders
   (`ADYEN_TEST_KEY_PLACEHOLDER`, zero-UUID); the four historical fingerprints pinned in
   `.gitleaksignore`; rescan = **no leaks found**. ⚠️ OWNER ACTION: confirm the old
   `AQE1hmfxKIPuJvh5BA` string was never a real Adyen key — it remains visible in git
   history. If it was real, revoke/rotate it in the Adyen Customer Area.

**Done when:** all CI badges green, history scan recorded clean (or cleaned), E2E no
longer allowed to fail silently.

Suggested commits: `ci(infra): unblock e2e first run`, `chore(monorepo): ignore known test dsn in gitleaks`

## Part B — The embarrassing pages (point 4) — ~2–3 hours

Execute `docs/plans/phase-1-alpha-hardening.md` §1.8 exactly (keep `/catalog/attributes/*`):

1. Admin: remove `routes/(auth)/sign-in-2.tsx`, `sign-up.tsx`, `forgot-password.tsx`
   (+ their mock form components and link sites: `user-auth-form.tsx` forgot-password
   link; check `otp.tsx` — if it's the real admin OTP flow, KEEP it).
2. Admin: remove template `features/settings/display|appearance` pages + routes;
   keep the settings shell and `settings/store`.
3. Admin: remove `features/users/` (faker 500 users) + `/users` route; verify
   `features/user/users` unaffected; drop `faker` dep if now unused (`pnpm knip` helps).
4. Storefront: delete orphaned `coming-soon-panel.tsx`; remove mock saved-cards page +
   nav entry + `saved-cards.tsx`; CHANGELOG note "returning with card storage".
5. Verify: `pnpm build && pnpm lint` (admin), `pnpm build` (storefront); click through
   every nav section on the demo.

**Done when:** a stranger's first admin session cannot find a fake screen.

Suggested commits: `refactor(admin): remove template auth and settings leftovers`,
`refactor(admin): drop faker users feature`, `refactor(storefront): remove mock saved cards`

## Part C — phpstan debt, direction reversed (point 3) — ongoing, start now

The baseline refreshed to **3,240** entries. Target from the lifecycle doc: **< 500 by the
beta gate**; alpha.1 ships with an honest number in the release notes.

1. Add a `phpstan` CI job (analyse with baseline; fails on new errors) so the number
   can only shrink from today.
2. Bite off modules smallest-first (Inventory 31, Audit 32, Setting 45, User 52, Media 70,
   Contact 72, Tax 83 …) — fix, regenerate baseline, commit per module. Repeat until
   momentum runs out or 6 modules are clear; the rest continue post-launch at cadence
   (Phase 3 §3.1 release SOP gets a "baseline shrank by N" line).
3. Rule for new code from today: new errors never enter the baseline — CI enforces it.

Suggested commits: `ci(infra): run larastan against the baseline`, `refactor(backend/{module}): clear phpstan baseline entries`

## Part D — Phase 1 leftovers: Scramble, S3, Shipping/Menu tests

Execute the phase-1 plan sections verbatim (they are still accurate):

1. **§1.6 Scramble** — publish config, register routes on `api/v1`, bearer security
   scheme, return types on the ~11 untyped controllers, `SCRAMBLE_DOCS_ENABLED` gate,
   export `openapi.json` in CI, re-add the README API-docs claims (now true).
2. **§1.5 S3 media** — `league/flysystem-aws-s3-v3`, fix `MediaResource` URL + 
   `DeleteMediaAction` to respect the record's disk, `Storage::fake('s3')` tests, docs.
3. **§1.7 tests** — Shipping admin CRUD + shipment transitions; Menu update/delete +
   permission denial; Setting permission/validation. Patterns and fixtures are specified
   in the phase-1 plan.

**Done when:** Phase 1 exit criteria box is fully ticked except the baseline number.

## Part E — FX rate auto-sync (your feature; upgrades the multi-currency claim)

Goal: admins can opt into automatic `exchange_rate` updates from an external FX API.
Manual rates remain the default and always remain editable.

1. **Provider abstraction (Core, near the currency CRUD):**
   `FxProviderInterface { fetchRates(string $baseCode): array<string, string> }` with a
   driver map in `Modules/Core/config/fx.php` (Payment-module style): v1 ships ONE
   provider — openexchangerates.org (free tier, 1k req/month, one endpoint, JSON) — with
   the base URL configurable so exchangerate.host/ECB drop in later.
2. **Admin surface:** extend the settings + currencies page — enable auto-sync, provider
   API key (stored in Setting, env fallback `FX_API_KEY`), sync interval. Permission
   derives from route names as usual.
3. **Sync engine:** artisan `currencies:sync-rates` — loads the default currency as base,
   calls the provider once, updates `exchange_rate` on every ACTIVE, NON-DEFAULT currency;
   never touches is_default/is_active; records `fx_last_synced_at` + per-currency drift
   in a settings row (or a tiny `core_fx_syncs` log table if audit matters — prefer the
   settings row). Scheduler entry (daily) only when enabled. Admin "Sync now" button hits
   the same command.
4. **Safety:** sync failure = log + toast, rates unchanged; provider returns unknown code
   for a currency = skip that currency, never zero/null it; rates validated by the same
   regex `Money::convert` enforces.
5. **Tests:** `Http::fake` provider responses (happy path, partial coverage, timeout,
   malformed rate skipped, key missing); feature test for the admin endpoints +
   permissions; unit test for provider response parsing.
6. **Bruno:** add `core/fx-sync.bru` requests; admin i18n keys in 6 locales.

Suggested commits: `feat(backend/core): add fx rate provider abstraction and sync command`,
`feat(admin): add fx auto-sync settings and sync-now action`

## Part F — Honest claims + market what exists (points 2 & 5) — ~2 hours

1. **README multi-currency row, exact wording:** "Display-currency conversion with
   admin-managed or auto-synced FX rates; per-currency price lists on the roadmap."
   Never the bare word "multi-currency" without the footnote — in README, comparison
   table, and any launch post.
2. **Comparison table** (README section, data vintage noted): include rows competitors
   don't publish — **Returns & refunds**, **8 payment gateways with webhook
   verification**, **RTL locales (fa, ar)**, **e2e + 700+ backend tests**, **Bruno
   collection (430 requests)**, **OpenAPI docs**, **shared-hosting deploys**. This is
   point 5: claim the machinery you already own.
3. **CHANGELOG `[Unreleased]`** gains the returns/refunds + FX-sync + locales entries
   (some are under-documented today).
4. Re-run the README truth grep from Phase 0 §0.2 — new claims must all be real.

Suggested commit: `docs(monorepo): honest multi-currency claim and full feature table`

## Part G — Launch (points 1 & 7): handoff to Phase 2

Preconditions (all previous parts done): Phase 1 exit criteria ticked, CI green
incl. e2e without `continue-on-error`, history scan clean.

1. Execute `docs/plans/phase-2-launch.md` in full — tag **`v0.1.0-alpha.1`** (marked
   pre-release), assets (the launch GIF should literally be a screen recording of the
   Playwright e2e run — point 1's cheapest win), channel sequence, launch-day runbook.
2. **Bus-factor countermeasures from day one** (point 7):
   - 10 real `good-first-issue` labels live at launch (locale requests + small fixes)
   - translator/contributor credit in every release note
   - Discussions seeded with 2–3 threads; every launch-channel visitor invited into one
   - `docs/showcase.md` stub published — "first store here" is the strongest possible ad
3. The week after launch: fix-forward cycle, `alpha.2` per the lifecycle doc, baseline
   continues shrinking (Part C cadence).

---

## Exit criteria (this plan done ⇔ ready to launch)

- [ ] CI: all workflows green, e2e strict, gitleaks history scan recorded
- [ ] Zero fake/mock screens reachable in admin or storefront
- [ ] phpstan CI job live; baseline ≤ ~2,700 at alpha.1 (strictly lower than 3,240) with
      the < 500-by-beta trajectory documented in the release notes
- [ ] Scramble-served `/docs/api` linked from README; S3 media documented + tested;
      Shipping + Menu tests merged
- [ ] FX auto-sync shipped behind a setting; multi-currency README claim honest
- [ ] Comparison table credits returns/refunds, gateways, RTL, testing
- [ ] Tag `v0.1.0-alpha.1` + Phase 2 runbook executed
