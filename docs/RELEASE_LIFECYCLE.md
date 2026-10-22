# Release Lifecycle — alpha → beta → rc → stable → 1.0

> Companion to `docs/ROADMAP.md` (what to build) and `docs/plans/` (how): this doc
> defines **which release stage Baxela is in, what each stage allows, and the gate
> criteria for moving to the next one**. Dates are projections from current velocity;
> **gates are the source of truth, dates are not** — advance when the gate is met, not
> when the calendar says so.
>
> Created: 2026-10-22 · Scheme adopted: `v0.1.0-alpha.1 → alpha.2 → beta.1 → rc.1 → 0.1.0`
> (dot-separated pre-release identifiers so `alpha.10` sorts after `alpha.9`).

## At a glance

| Stage | Means | Allowed changes | Cadence | Projected window |
|---|---|---|---|---|
| `0.1.0-alpha.N` | Feature development in public | Anything, incl. breaking (must be in release notes) | Every 2–3 weeks | Nov 2026 – Jan 2027 |
| `0.1.0-beta.N` | Feature-complete for 0.1; hardening | Bug fixes + safe additions; **no breaking changes** | Every 1–2 weeks | ~Jan–Feb 2027 |
| `0.1.0-rc.N` | Code freeze, blockers only | Bug fixes that block release | As needed (days) | ~Feb 2027 |
| `0.1.0` (stable) | First "safe to run" release | 0.x rules: breaking ⇒ next MINOR, fixes ⇒ PATCH | Every 4–6 weeks | ~Feb 2027 |
| `0.2.0` … `0.x` | Minors on a stable base | New features, breaking changes cost a minor | Every 4–6 weeks | Mar 2027 → |
| `1.0.0` | Stability promises (see gate) | Backward-compatible within major | 6–8 weeks | When gates met — **do not rush** |

Every pre-release tag is marked **pre-release on GitHub** so the "Latest" badge stays
reserved for `0.1.0`. The Phase 2 launch (Show HN / Laravel News / etc.) rides
`v0.1.0-alpha.1` — you only get that moment once, and it costs nothing to spend it on
the first *stable-ward* tag.

## Stage 1 — `v0.1.0-alpha` (development in public)

**Entry gate (= Phase 1 exit + Phase 2 done):** security pass green, e2e happy path
green, multi-currency storefront shipped, 6 locales, S3 media, Scramble live, no
template leftovers, phpstan baseline < 500, launch assets ready, repo public, demo live.

**Working rules:**
- New features and breaking changes are fine — every release note lists breaking items
  (env vars, migrations, API shape) under a "Breaking" heading.
- Fix forward; no upgrade path is promised between alphas.
- Each alpha needs a one-line headline a stranger can repeat ("Meilisearch",
  "6 more locales") — release SOP in `docs/plans/phase-3-growth.md` §3.1.

**Planned alpha content** (maps to ROADMAP Phase 3 items):
- `alpha.1` — public launch tag (Phase 2).
- `alpha.2` — storefront currency polish, admin currency UI, first feedback fixes.
- `alpha.3` — Meilisearch (+ the blog post that ships with it).
- `alpha.4` — community locales, first outside contributors' PRs.
- `alpha.5` — buffer: security fixes, e2e stabilizing in CI (`continue-on-error` flips
  off here at the latest).

**Exit gate (alpha → beta), ALL required:**
- [ ] Public roadmap's 0.1-scope features shipped or explicitly re-scoped to a later minor
- [ ] e2e workflow green in CI (no `continue-on-error`)
- [ ] phpstan baseline < 500 and no new entries per release
- [ ] Zero known open security issues; admin-route audit test still green
- [ ] Quickstart dry-run from clean clone passed on the last two alphas
- [ ] At least one **real store** (or serious pre-launch deployment) running an alpha
- [ ] First contribution from outside the owner merged (locale, fix, or docs)

## Stage 2 — `v0.1.0-beta` (feature freeze for 0.1)

**Entry:** alpha exit gate met. New features now target `0.2.0` unless they fix what
0.1 ships.

**Working rules:**
- Bug fixes, docs, performance, i18n completeness — no new surfaces.
- A breaking change in beta needs a written migration note and a very good reason;
  if it can wait for `0.2.0`, it waits.
- Docs completeness sprint: install guides (Docker / prod compose / shared hosting),
  Scramble-published API docs linked everywhere, FAQ.

**Exit gate (beta → rc):**
- [ ] Two consecutive betas with zero critical (checkout/auth/payment/data-loss) bugs
- [ ] Docs listed above complete; CHANGELOG accurate for every alpha→beta version
- [ ] Demo redeployed on the latest beta and smoke-tested via the e2e spec
- [ ] Feedback items from launch channels triaged: fixed, scheduled, or won't-fixed

## Stage 3 — `v0.1.0-rc` (code freeze)

1–2 weeks. Only release blockers: crashers, data-integrity bugs, security, install
failures. Everything else → `0.1.1` or `0.2.0`. Exit: one clean rc with no new
blockers → **tag `v0.1.0`**, uncheck pre-release, that's the "Latest" badge moment.

## Stage 4 — `0.1.0` stable and the 0.x line

- **Patches** (`0.1.1`, `0.1.2`): bug/security fixes only, no migrations where avoidable.
- **Minors** (`0.2.0`, `0.3.0`): features; in 0.x a minor may break things (that's the
  0.x contract) — release notes carry the migration section. Multi-vendor (decision doc
  per `docs/plans/phase-3-growth.md` §3.5) lands as a minor, likely `0.3.0`/`0.4.0`,
  possibly as its own beta line (`0.4.0-beta.1`) if it needs a long preview.
- Every stable release repeats the SOP: CHANGELOG, tag, GIF, X post, comparison-table
  refresh.

## The `1.0.0` gate — stability promises, earned not scheduled

Ship `1.0.0` only when ALL of:
- [ ] The public API (`api/v1`) shape is documented (Scramble) and you commit to
      backward compatibility within a major
- [ ] Module contracts layer (`Core/app/Contracts`) documented as an extension surface —
      the plugin/override story a marketplace needs
- [ ] 3+ real stores in the showcase, running production traffic
- [ ] e2e suite covers checkout + admin critical paths; phpstan baseline at or near zero
- [ ] A security review pass (self-audit at minimum) on auth/payment/webhook surfaces
- [ ] Upgrade path documented from at least `0.4.x`

Until then, 0.x is the honest label. `1.0.0` is a promise to strangers; 0.x is a
conversation with early adopters.

## Rules that hold at every stage

1. The release SOP (`docs/plans/phase-3-growth.md` §3.1) runs for every tag — CHANGELOG
   entry, GitHub Release, headline, GIF.
2. Quickstart from a clean clone is verified at least every other release.
3. Never two pre-release lines at once (no `0.1.1-beta` while `0.2.0-beta` is in flight).
4. Dates in this doc are projections from the Oct 2026 velocity; the gates don't move
   when the dates do. If a gate slips a month, the stage stays — that's the system working.

## How the existing plans map onto stages

| Plan | Stage |
|---|---|
| Phase 0 (done) + Phase 1 | Pre-alpha — everything before `v0.1.0-alpha.1` |
| Phase 2 (launch) | The `alpha.1` moment |
| Phase 3 (growth) | Spans alpha → `0.1.0` → early 0.x |
| Multi-vendor proposal + build | A 0.x minor (target `0.3.0`/`0.4.0`) |
| 1.0 gate above | Replaces the old "alpha suffix drops at v0.6" idea |
