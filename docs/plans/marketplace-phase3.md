# Plan M — Marketplace phase 3: the composer registry app (`baxela-marketplace`)

> **Purpose of this document:** Self-contained execution plan for roadmap Phase 3
> (`docs/EXTENSION_MARKETPLACE_ROADMAP.md` §4 "Marketplace app") — the machine room of the
> extension marketplace: a composer registry, an ingestion pipeline, a catalog API, and a
> minimal back office. Written after the E.2 system-proof gate passed (2026-11-10), which
> is the explicit precondition the roadmap sets for this phase. Like gap-b1/gap-c/e0-e2,
> this document stands alone: verified state, design decisions, build order, edge cases,
> tests — readable without conversation history.
>
> **Created:** 2026-11-10 · **Owner:** solo maintainer · **Status:** **approved 2026-11-10 —
> review pass 2 (9.2/10, green light); its four implementation notes are folded in; M.0 started**
>
> **Execution order (strict):** **M.0 → M.1 → M.2 → M.3 → M.4 → M.5**. Each slice lands
> green and committed before the next starts. Size estimates are initial, not commitments:
> M.0 ≈ 0.5 d, M.1 ≈ 1 d, M.2 ≈ 2 d, M.3 ≈ 0.5 d, M.4 ≈ 0.5 d, M.5 ≈ 0.5 d (≈ 5 d total).
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. This app is a SEPARATE
> plain Laravel repo (roadmap decision #5) — the monorepo's module conventions (schema
> constants, action/controller split) do NOT apply; plain Laravel conventions do. Conventional
> commits apply per its own history. Never commit unless asked; heavy commands `nice -n 10`;
> Larastan before Pest; a green happy-path test is not a reliable engine — the tests below
> target the yank/refusal/drift/downgrade paths, not just sync-then-install.

---

## 1. Where this fits

| Slice | Content | Why this order |
|---|---|---|
| M.0 | App skeleton + `packages`/`releases`/`audit_log` schema | Everything else is routes over these rows |
| M.1 | Composer registry: `packages.json` + dist zips + reserved auth | The artifact `baxela:install` consumes; testable with a hand-seeded row before any GitHub code exists — registry correctness isolated from ingestion complexity |
| M.2 | Ingestion: `SourceProvider` + `marketplace:sync` + package-contract enforcement + secure archive processing | Fills the registry with real data; the only slice that talks to GitHub |
| M.3 | Catalog API: search/list + `POST /api/packages/updates` | Trivial over populated tables; the updates endpoint pre-shapes roadmap Phase 5's badge |
| M.4 | Back office (Filament): yank, releases, stats, audit | Needs M.1–M.3 rows to administer |
| M.5 | End-to-end proof gate | Sandbox installed from the live registry on a clean checkout — the phase's exit |

Not in this plan (Phase 4+): public frontend, GitHub OAuth submissions, review workflow
(pending/approved/rejected), GitHub webhook auto-re-sync, GitLab sources, paid licensing,
API rate limiting, download analytics beyond counters.

## 2. Verified state register (2026-11-10)

- ✅ The E.2 gate passed today: `baxela:install` runs the full front door through a path
  repository, with the provider-autoloader, `:*` root-constraint and boot-guard hardening
  (monorepo commits `65929b8c`, `f197678e`, `4389b6b2`, `35f5ee3b`). `App\Baxela::VERSION`
  is `0.1.0`; sandbox `compat.core` is `^0.1`.
- ✅ The sandbox package exists at `apps/backend/extensions/baxela-sandbox-gateway` as its
  own local git repo — tags `v0.0.1`, `v0.0.2`, **no remote yet** (pushing it is the first
  M.5 step and needs working GitHub auth — see the owner actions below). Note: the sandbox
  declares an explicit `version` field in composer.json (the path-repo stability workaround)
  — the M.2 version-agreement rule governs how that interacts with tag-derived versions.
- ✅ The Baxela app skeleton ships exactly one repositories entry today
  (`{"type": "path", "url": "extensions/*"}`, commit `f197678e`). The marketplace URL
  becomes a **second** entry when the registry is live — the path entry stays for dev.
- ⚠ Composer registry format: `GET /packages.json` returns
  `{"packages": {"vendor/name": [<full package metadata incl. version, dist{type,url,shasum}, require, extra…>]}}`;
  `shasum` is the SHA-1 of the served zip; the registry **must inject the `version` field**
  — most packages do not declare it in composer.json; it derives from the git tag. Verify
  against current composer docs while building M.1/M.2 — stable for a decade, but the
  details are load-bearing.
- ⚠ GitHub API facts: `GET /repos/{full}/tags` (paginated, 30/page) returns each tag's
  **commit sha** (for annotated tags this is the tag-object SHA — resolve through
  `GET /repos/{full}/git/tags/{sha}` to the commit SHA, or store the ref SHA consistently
  and compare like-for-like); `GET /repos/{full}/contents/{path}?ref={tag}` for file fetch;
  tarball via `https://codeload.github.com/{full}/tar.gz/refs/tags/{tag}` (no auth for
  public repos). **Request budget (review correction):** a sync costs the tag pages PLUS
  one manifest fetch PER NEW TAG — not "≈1 + tags"; with commit-SHA skipping, unchanged
  tags cost only the tag-list pages. Anonymous rate limit 60 req/hr per IP.
- ⚠ **Owner action (host machine):** the global GitHub OAuth token in
  `~/.config/composer/auth.json` is dead (401 — verified 2026-11-10). It does not affect
  this app's own env, but it blocks pushing the sandbox to its remote and any host-side
  authenticated GitHub call. Renew or remove before M.5.
- ⚠ Filament compatibility with Laravel 13: verify the current Filament major supports it
  at M.0 time; fallback is a plain Blade panel (the back office is 3 screens — Filament is
  convenience, not a dependency).

## 3. Slice M.0 — Skeleton & schema (~0.5 d)

### Design decisions

1. **Plain Laravel app, new repo `baxela-marketplace`** in the same GitHub org. No nwidart,
   no modules — roadmap decision #5. Planning lives in the monorepo (this file); code does not.
2. **Tables** (plain migrations, not schema-constant style):
   - `packages`: `id`, `name` (unique — `vendor/baxela-slug`), `source_repo` (unique — the
     GitHub `owner/repo` it ingests from; one name = one repo, forever), `type` (**from
     `extra.baxela.type`, the extension's functional category — NEVER the composer
     top-level `type`, which is always `baxela-extension`; the catalog would otherwise
     classify every extension identically**), `description`, `status`
     (`active`|`yanked`), `timestamps`. Index `status`.
   - `releases`: `package_id` FK (**restrictOnDelete — the registry is append-only; no
     cascade may ever erase release history**), `version` (normalized semver string —
     `v0.0.1` normalizes to `0.0.1`; unique with `package_id`), `source_tag` (the original
     git tag; unique with `package_id` — upstream identity), `source_commit_sha` (the
     resolved commit SHA at ingestion — immutability anchor), `source_composer_json_hash`
     (sha256 of the tag's composer.json — manifest drift detection), `dist_path`,
     `dist_shasum` (sha1 of the generated zip), `dist_size`, `metadata` (json — the
     validated composer.json snapshot: `extra.baxela` incl. `compat.core`, `require`,
     `license`), `status` (`active`|`yanked`), `download_count` (unsigned bigint, default
     0), `published_at`, `timestamps`. Indexes `(package_id, status)` (active-release
     lookups), `status`.
   - `audit_log`: `id`, `actor_email`, `action` (`release.yank`, `release.unyank`,
     `package.yank`, `package.unyank`, `package.sync`), `subject` (package name [+ version]),
     `reason` (required on yank), `meta` (nullable json), `timestamps`. The `status`
     columns say what IS; this table says what happened, to whom, by whom, and why.
3. **Registry is append-only + yank.** Nothing is ever deleted; releases and packages get
   `yanked`, which removes them from `packages.json` and 404s their dist. Mirrors the
   roadmap's no-downgrade, never-rewrite promise and keeps audit trails for the paid era.
4. Dependencies: `composer/semver` (compat filtering AND version ordering — this repo
   declares its own; no shared code with Baxela), Filament (⚠ see register),
   Pest + Larastan like the backend.
5. **`marketplace:user {email}`**: prompts for a password (or generates and prints one),
   idempotent — an existing user gets a password reset, stated plainly in the output.
   Panel registration is disabled (no public user routes exist at all); Filament panel
   access is the only auth surface.

### Work items

- [x] Scaffold the app (Laravel current, Pest, Larastan, Pint). *(done 2026-11-10 —
      Laravel 13.40, `baxela/marketplace`, sibling repo at `baxela-hq/baxela-marketplace`;
      `composer test|analyse|pint` scripts; own git history)*
- [x] All three migrations + models with casts; `Release::latestActive()` helper that
      **orders by composer/semver comparison, never string sort or `latest()`** (see M.3).
      *(done 2026-11-10 — executed as `Release::latestActiveFor(Package)` +
      `Package::latestActiveRelease()`; the `0.0.10 > 0.0.9` ordering is tested)*
- [x] `marketplace:user` command. *(done 2026-11-10 — idempotent create/reset, interactive
      secret prompt with a printed fallback for non-interactive runs, no public
      registration; both paths tested)*

## 4. Slice M.1 — Composer registry (~1 d)

### Design decisions

1. **`GET /packages.json`** renders every ACTIVE release of every ACTIVE package in
   composer-registry shape: the stored `metadata` snapshot **with the `version` field
   injected from the release row** (tag-derived — see M.2) + `dist.url` pointing at the
   marketplace's own `/dist/...` route. A package whose every release is yanked is
   **omitted entirely** (same as a yanked package — never an empty version array).
   Flat document, no provider-groups/pagination — correct until dozens of packages.
2. **Self-hosted dist zips**: `GET /dist/{vendor}/{name}/{version}.zip` streams from
   `storage/app/dist`, increments `download_count`, 404s for yanked. Self-hosting (not
   GitHub archive passthrough) is what makes yank real and paid gating possible later.
   **Dist responses carry `Cache-Control: no-store`** — a yanked zip must not survive in
   any intermediary; metadata (packages.json) may cache briefly, archives never.
3. **Auth reserved from day one**: `EnsureRegistryToken` middleware on `packages.json` +
   dist. Config `registry.token` empty (v1 default) → anonymous; set → composer clients
   authenticate with HTTP basic (any username, the token as password). Safeguards:
   constant-time comparison (`hash_equals`), HTTPS enforced when `app.env` is production
   (reject plain http), the token never appears in logs or error output, malformed
   `Authorization` headers → 401 without exception detail. **Scope note:** this single
   global token is the private-registry capability — per-customer auth for paid extensions
   is Phase 7 and will be a different mechanism on the same seam.
4. **Metadata cache, explicitly invalidated:** `packages.json` cached 60 s, and the cache
   key is flushed by every mutation — sync, package yank/unyank, release yank/unyank.
   ETag/Last-Modified support is a later optimization, noted for Phase 4; v1 ships none.

### Work items / tests

- [ ] `packages.json` route + the `RegistryDocument` builder (version injection).
- [ ] Dist route with streaming, counting, yank-404, `no-store`.
- [ ] `EnsureRegistryToken` middleware + config; both states tested.
- [ ] Tests (hand-seeded rows, no GitHub): document shape incl. injected version; yanked
  release absent; yanked package omitted; all-releases-yanked package omitted; dist 404
  on yank + no-store header; token set → 401 without/with wrong credentials and with
  malformed headers; token empty → anonymous OK; cache bust after every mutation kind.

## 5. Slice M.2 — Ingestion (~2 d)

### Design decisions

1. **`SourceProvider` interface** (`tags(repo)` → list of `{tag, sha}`, `file(repo, ref,
   path)`, `tarball(repo, tag)`), GitHub-only implementation behind Laravel HTTP, token
   optional from `services.github.token` (anonymous allowed). GitLab is Phase 7; the
   interface is the seam. **HTTP discipline:** per-request connect/total timeouts; bounded
   retry with backoff on 5xx and rate-limit 403s; rate-limit exhaustion surfaces as a
   clear command error (with reset time), never a silent partial sync.
2. **Sync algorithm — explicit, SHA-first (review correction):**
   1. Fetch upstream tags (all pages).
   2. Filter to stable semver (`v?X.Y.Z`); prerelease tags are skipped with a notice.
   3. Resolve each tag to its commit SHA (annotated tags resolve through the tag object;
      store the COMMIT sha).
   4. Tag already stored with the same SHA → **skip without any further API call or
      download** (this is what bounds the request budget).
   5. New SHA for an existing `source_tag` → **drift**: report, keep the stored release
      untouched, never replace.
   6. New tag → ingest: fetch composer.json at the ref → hash it (stored for future
      manifest-drift detection) → enforce the contract → build the dist → create the row.
3. **Version derivation and the agreement rule (review correction):** the release's
   `version` is **derived from the git tag** (normalized: `v0.0.1` → `0.0.1`; original tag
   preserved in `source_tag`). If composer.json *declares* a `version` that disagrees with
   the normalized tag → reject the tag with that reason (ambiguity is a contract
   violation — the sandbox's explicit `version` field agrees with its tags, so it passes).
   Two tags normalizing to the same version (`v1.0.0` + `1.0.0`) → the second is rejected
   as a duplicate; invalid/unparseable tags are rejected with reason. The registry
   document's `version` always comes from this derived field — composer can resolve what
   we published.
4. **Package contract enforcement** (roadmap §3): name regex
   `^[a-z0-9][a-z0-9_.-]*/baxela-[a-z0-9][a-z0-9_.-]*$`, `type: baxela-extension`,
   `extra.baxela` completeness (name, slug, `type` from `extra.baxela.type`, parseable
   `compat.core`), license present. **Identity consistency (review 2):** `extra.baxela.slug`
   must equal the composer package name (the E.2-established convention), and package
   identity — `name` and `source_repo` — is **locked at first successful ingestion**: a
   later release presenting a different name/slug is rejected as identity drift, never
   re-associated. Violations skip the offending tag with a tabled reason; a repo that is
   gone, private, or serves no valid tags fails the command.
5. **Secure archive processing (review correction):** the marketplace never executes
   extension code, and it must not be compromisable by a hostile archive either. Limits
   are config (`ingest.*`), defaults in parentheses:
   - Max compressed download size (10 MB) and max total extracted size (50 MB) — a
     compression ratio beyond ~100× is rejected as a probable bomb regardless of sizes.
   - Max entry count (2,000), max individual file size (10 MB).
   - Reject entries with traversal (`../`), absolute paths, or symlinks; reject archives
     that don't contain `composer.json` at the package root (after the GitHub
     `repo-tag/` prefix strip).
   - Extraction happens into a fresh, unpredictable temp directory (`bin2hex(random)`
     under `storage/app/ingest/`), never a predictable shared one; cleanup ALWAYS runs,
     including on failure.
   - **Resource exhaustion (review 2):** the compressed cap is enforced **while
     streaming the download** (bytes counted as they arrive — never whole-response into
     memory); the extracted-size limit counts **actual bytes written during extraction**,
     never values trusted from archive metadata; entry names must be **unique after path
     normalization** (duplicate normalized paths rejected); entries that are anything but
     regular files or directories are rejected; the whole ingest runs under a hard
     processing timeout (config, default 120 s) so pathological archives cost bounded CPU.
   - The generated zip is rebuilt from validated entries only (no passthrough copying of
     foreign archive structure), and **verified before publishing**: the finished zip is
     re-opened, its entry list re-checked, `composer.json` confirmed at root, and its
     sha1 recorded. A release row is created only after that verification passes.
6. **Publication transaction and recovery (review 2):** build and verify in temp storage
   → compute checksums → move the artifact to its final immutable path (`dist/{vendor}/
   {name}/{version}.zip`) → insert the release row inside a DB transaction → if the
   insert fails, delete the moved artifact; if storage fails, no row exists. An artifact
   at the final path without a matching row is an interrupted publication: the next sync
   treats it as incomplete and rebuilds it, so recovery is simply "sync again" — no
   manual cleanup, no orphaned offers.
7. The sandbox (`baxela/baxela-sandbox-gateway`) is the designated first ingestion.

   **Package-level yank precedence (review 2):** a yanked package overrides its release
   states everywhere — registry document, dist routes, catalog, updates endpoint — and
   unyanking a package restores visibility **only for individually active releases**
   (release-level yanks survive package-level unyank; the two states compose, never
   overwrite).

### Work items / tests

- [x] `SourceProvider` + `GithubSourceProvider` (HTTP client, `Http::fake` in tests).
      *(done 2026-11-13 — the ref-SHA like-for-like variant of the register's
      sanctioned alternatives: the tags endpoint's `commit.sha` is stored as
      `source_commit_sha` and compared like-for-like, costing zero API calls
      for unchanged tags; recreating an annotated tag against the same commit
      reports a false drift — a safe, report-only failure mode. Tarballs
      stream chunk-by-chunk with the compressed cap enforced mid-transfer.)*
- [x] `marketplace:sync` with the contract validator as a pure, unit-tested class.
      *(done 2026-11-13 — `PackageSynchronizer` + `PackageContractValidator`;
      identity, version agreement, contract skips tabled per tag.)*
- [x] Dist zip builder under the ingestion limits + post-build verification.
      *(done 2026-11-13 — a hand-rolled streaming USTAR reader
      (`TarStreamReader`: typeflag-level control, no PharData) feeding
      `ArchiveProcessor`: caps counted as bytes arrive/are written, traversal/
      symlink/absolute/duplicate/non-regular rejection, bomb-ratio guard, hard
      timeout, fresh temp dir, zip rebuilt from validated entries and
      verified before publishing; move-then-insert publication with
      compensating delete.)*
- [x] Tests: contract refusals (bad name, wrong composer type, missing manifest, missing
  license, unparseable compat) each skip with reason; declared-version disagreement
  rejected; `v`-prefixed tag normalized (and metadata version injected accordingly);
  duplicate normalized version rejected; idempotent re-sync (SHA-equal → no fetch);
  SHA-changed tag → drift reported, release untouched; repo-gone failure; rate-limit
  403 → clear exhaustion error; archive attacks (traversal entry, symlink entry, bomb
  ratio, oversized entry, missing root composer.json) each rejected without residue;
  full fake happy path seeding two versions.
      *(done 2026-11-13 — 35 new tests: 13 validator unit cases, 10 archive-
      processor cases incl. hostile fixtures from a hand-rolled USTAR writer,
      12 sync command cases with `Http::fake` + `preventStrayRequests` and a
      request-count budget proof (sync 1 = 5 requests, re-sync = 1). Suite:
      58/58, Larastan level 6 clean, Pint clean.)*

## 6. Slice M.3 — Catalog API (~0.5 d)

### Design decisions

1. **`GET /api/packages`** — public list/search (`?q=` over name+description, `?type=` on
   `packages.type` = the extension category), each row: name, type, description, latest
   active version + its `compat.core`, per-tag version list.
2. **`POST /api/packages/updates`** — body `{core: "0.1.0", installed: {"baxela/x":
   "0.0.1"}}`. **Selection is semver-ordered via composer/semver (`Comparator`), never
   string sort or DB ordering** — `0.0.10` is newer than `0.0.9`. Explicit response
   contract per slug (review correction):

   | state | when | `latest` | `update_available` |
   |---|---|---|---|
   | `up_to_date` | newest compatible release ≤ installed | that release | false |
   | `update_available` | newest compatible release **semantically newer** than installed | that release | true |
   | `installed_newer_than_registry` | installed is semantically newer than everything offered | newest compatible | false |
   | `no_compatible_release` | releases exist but none satisfy the submitted core | null | false |
   | `unknown_package` | slug not in the registry | null | false |
   | `package_yanked` | package status yanked | null | false |

   Rules: `update_available` requires a semver-newer candidate — a missing or yanked
   current release is **not** an update signal by itself; a yanked CURRENT version is
   reported via a `current_yanked: true` flag (the merchant should know) while updates
   are still evaluated normally; an installed version newer than the registry's latest
   is **`installed_newer_than_registry`** (review 2: truthful — the marketplace simply
   has nothing newer; `latest` still reports what IS offered), never silently
   `up_to_date`; prereleases never exist in the registry (M.2 skips them), so exclusion
   is structural. Same semver rule as the core guard and the install hard stop — one
   compatibility definition, three enforcement points. This is roadmap Phase 5's badge
   endpoint.

### Work items / tests

- [x] Both endpoints; request validation (`core` semver, installed map shape).
      *(done 2026-11-13 — `PackageCatalog` + `UpdateResolver` (the explicit
      state machine), `CatalogController` + `UpdatesController`; routes
      `GET /api/packages`, `POST /api/packages/updates`, public by design.)*
- [x] Tests: compatible update offered; **newer-but-incompatible NOT offered**; semver
  ordering (`0.0.10` > `0.0.9`) in both latest-selection and update comparison;
  never-downgrade (installed `0.0.11`, latest `0.0.10` → `installed_newer_than_registry`);
  installed newer than registry → `installed_newer_than_registry` with `latest` still
  reported; unknown slug → `unknown_package`; yanked package → `package_yanked`;
  yanked current version → flag set, updates still evaluated; yanked release never
  offered as `latest`; no compatible release → `null` latest.
      *(done 2026-11-13 — 16 new tests (5 catalog + 11 updates); fixtures are
      deliberately inserted NEWEST-FIRST so any reliance on insertion/database
      ordering would fail. Suite 74/74, Larastan clean, Pint clean.)*

## 7. Slice M.4 — Back office (~0.5 d)

- [x] Filament panel (fallback plain Blade if ⚠ L13 support lags): Package list/view with
      releases; yank/unyank actions at package AND release level; download counters;
      audit-log viewer.
      *(done 2026-11-13 — the plan's own fallback: plain Blade, no new dependencies.
      Filament is convenience, not a dependency, and the back office is three screens:
      packages list, package detail (release table + actions), audit viewer.)*
- [x] Yank/unyank require explicit confirmation and a **reason**; every action writes the
      `audit_log` row (actor, action, subject, reason).
      *(done 2026-11-13 — `YankService`: state flip + audit row + registry-cache flush on
      every mutation; no-ops rejected without audit rows.)*
- [x] No create/edit resources — data enters only through sync; editing would fight the
      append-only registry.
- [x] Login via the M.0 user only; panel behind auth; registration disabled; nothing
      public except M.1/M.3 routes.
      *(done 2026-11-13 — session auth via the M.0 `marketplace:user` users; registration
      routes do not exist. 9 panel tests: auth gate, login, lists, yank/unyank at both
      levels incl. reason validation and no-op rejection, package-unyank precedence,
      audit viewer. Suite 83/83, Larastan clean.)*

## 8. Slice M.5 — Proof gate (~0.5 d, the phase exit) — **PASSED 2026-11-13**

All through the front door, on the **live registry** (a locally served instance is fine —
composer accepts `http://127.0.0.1:PORT` as a repository URL). Full log: the sandbox lives
at `baxela-hq/baxela-sandbox-gateway` (public, tags v0.0.1–v0.0.3); the proof checkout was
a fresh clone at `/tmp/baxela-clean`. Adaptation note: the yank scenarios were run against
the versions the checkout actually held at that point (yank-0.0.1 fresh-resolve check ran
post-v0.0.3, so the fresh resolve got 0.0.3; the yank-after-install scenario used the
stronger form — yanking the INSTALLED 0.0.3 while 0.0.2 remained installable). Every
contract property was proven; the scenario letter differed.

- [x] Owner action: renew the dead GitHub token; push the sandbox repo to its remote.
      *(done — the token lived at `~/.composer/auth.json` (the plan register's
      `~/.config/composer/` path was wrong for this machine); it was already half-gone,
      the remaining `github-oauth` entry was removed with `auth.json.bak-20261113` kept.
      Sandbox pushed to `baxela-hq/baxela-sandbox-gateway` with all tags.)*
- [x] `marketplace:sync baxela/baxela-sandbox-gateway` → package + v0.0.1/v0.0.2 releases;
      `packages.json` shows both with injected versions; dist zips download.
      *(live, anonymous GitHub API; the first live run exposed a real PAX-header bug —
      the tar reader consumed only the padding of pax/g payloads, desynchronizing on the
      codeload archive's `pax_global_header`; fixed with full payload consumption +
      per-entry `path`/`size` override support + regression tests, commit `aaa2eb7`.)*
- [x] Clean Baxela checkout (fresh clone, `/tmp`; path repo irrelevant there): add the
      marketplace repositories URL → `baxela:install baxela/baxela-sandbox-gateway` resolves
      over HTTP → checkout → signed webhook settles → `baxela:disable` → `baxela:remove`.
      *(done — install resolved 0.0.2 over HTTP, registered + enabled; disable and remove
      clean. Two environment notes: composer 2.11 hard-errors on the path repo when the
      `extensions/` dir is absent (an empty dir suffices), and `secure-http` must be false
      for the http://127.0.0.1 registry in the proof checkout. The webhook step is E.2
      vocabulary — no webhook exists in the phase-3 registry; "settles" here = the
      provider install completing, which the enabled row in `baxela:list` shows.)*
- [x] Tag `v0.0.3` (trivial change) upstream → re-sync → the clean checkout's
      `baxela:update` moves to it; `POST /api/packages/updates` with core `0.1.0` +
      installed `0.0.2` offers `0.0.3`.
      *(done — the checkout was pinned to 0.0.2 with the root constraint relaxed to `:*`
      (the baxela convention) since the tag now exists upstream; `baxela:update` moved the
      lock to 0.0.3 from the registry's own dist zip; the updates endpoint answered
      `update_available` + latest `0.0.3`. Found: composer's client-side metadata cache
      must be flushed for the registry before an update can see a newly synced version —
      the same cache-honesty property the last box tests, met from the other side.)*
- [x] Yank `0.0.1` in the back office (with reason; audit row written) → absent from
      `packages.json`, dist 404s; a fresh resolve still installs `0.0.2`.
      *(done via the real panel over HTTP — login, CSRF, reason; document dropped 0.0.1
      IMMEDIATELY (the mutation flush), dist 404, audit row recorded.)*
- [x] **Yank-after-install (review addition):** yank the INSTALLED version → the installed
      extension remains intact and functional, `baxela:update` reports no available
      update for it, no fresh resolve can obtain the yanked version, and a compatible
      other version still installs. Yanking never uninstalls or disables installed
      copies — registry policy, documented below.
      *(done with 0.0.3 installed and then yanked: `baxela:list` kept it enabled,
      `baxela:update` stayed at 0.0.3 — no forced downgrade to the older, active 0.0.2 —
      and scratch resolves installed 0.0.2 while an explicit `require :0.0.3` failed.)*
- [x] **Composer-client cache honesty (review 2):** resolve once BEFORE the yank (so
      composer's own metadata cache holds the pre-yank document), yank, wait out or
      flush past the metadata cache TTL, then resolve again with the real composer
      client — the yanked version must be uninstallable even from a checkout that
      previously resolved it. Cache invalidation is proven through the composer client,
      not only through Laravel's cache.
      *(done — the scratch resolver warmed the client cache with 0.0.3 pre-yank; after
      the yank + flushing the registry's metadata cache entry
      (`~/.composer/cache/repo/http---127.0.0.1-8090-packages.json`), the same resolver
      moved to 0.0.2 and `require :0.0.3` became unresolvable.)*
- [x] Register the second repositories entry decision: keep path entry; add the production
      registry URL to the Baxela app skeleton **when the marketplace is deployed publicly**
      (not at proof time — the skeleton must not point at a URL that doesn't exist yet).
      *(decision stands, unchanged — the monorepo skeleton still ships only the path entry.)*

**Phase exit:** all boxes green → tick roadmap Phase 3, then the Phase 4 plan (frontend +
submissions) gets written. *(roadmap ticked 2026-11-13; final registry state after the
proof: 0.0.1 yanked — the demonstration — 0.0.2/0.0.3 active, the whole exercise in the
audit log.)*

## 9. Policies this plan adds

1. **Append-only + yank.** No deletes, no mutation of ingested releases; upstream drift is
   reported, never applied. The registry is a promise, like the core schema promise.
2. **One name, one source repo, forever** (`source_repo` unique) — identity is not editable.
3. **Yank is an offer-withdrawal, never a revoke.** Already-installed extensions keep
   working; yank only removes the version from offer (metadata + dist) going forward.
4. **The marketplace never executes extension code**, and never trusts an archive:
   ingestion reads text and archives under explicit limits; validation is parsing, not
   booting.
5. **The auth pathway ships in v1** even though v1 is anonymous — retrofitting auth onto a
   live registry URL is the mistake the roadmap explicitly calls out. The global token is
   the private-registry capability; per-customer paid auth is Phase 7, same seam,
   different mechanism.
6. **Baxela's skeleton gains the registry URL only when the registry is public.**
7. **The marketplace stays independent of Baxela's runtime.** It understands composer
   metadata, extension manifests, compat constraints, artifacts and categories — it never
   loads the Baxela application, boots a provider, or depends on module classes. Coupling
   happens through the manifest contract and `composer/semver` only.

## 10. Explicit non-goals (silence reads as oversight)

Public browse/detail pages, README rendering, GitHub OAuth, submission flow and
pending/approved/rejected states, GitHub webhook auto-re-sync, major-bump re-flagging,
GitLab sources, paid/licensed enforcement (only the reserved token pathway exists),
provider-group pagination, API rate limiting, download analytics beyond a counter, i18n,
search beyond SQL LIKE, ETag/Last-Modified. All Phase 4+ per the roadmap.

## 11. Review trail

- 2026-11-10 — drafted from the roadmap Phase 3 outline + the E.2 verified state (§2).
- 2026-11-10 — external review pass 1 (**8/10**, architecture approved, one revision
  pass required): P1s — upstream tag/commit identity for real immutability and SHA-skip;
  archive-ingestion security spec; tag-derived version normalization with an agreement
  rule (composer rarely declares versions); semver-ordered selection (never string sort);
  explicit updates-endpoint state contract incl. no-downgrade and yanked-current
  handling; `packages.type` sourced from `extra.baxela.type`. P2s — cache invalidation
  matrix + `no-store` on dist; auth safeguards (constant-time, HTTPS, log hygiene) and
  the global-token-vs-per-customer distinction; corrected GitHub request budget;
  indexes + `restrictOnDelete` + `(package_id, source_tag)` unique; admin audit trail
  and yank confirmation; the M.5 yank-after-install scenario. All folded into this
  revision; M.2 estimate raised to ~2 d. Next: reviewer's second pass, then M.0.
- 2026-11-10 — external review pass 2 (**9.2/10, green light**): four implementation
  notes folded in without an architecture change — `installed_newer_than_registry` as a
  distinct updates state (truthful where `up_to_date` lied); archive processing hardened
  against resource exhaustion (streaming size caps counted as bytes arrive/are written,
  duplicate-normalized-path and non-regular-entry rejection, hard ingest timeout); the
  release-publication transaction with move-then-insert ordering and sync-as-recovery;
  package identity locked at first ingestion (name/slug/source_repo consistency) and
  package-yank precedence with release-level yanks surviving package unyank. Plus the
  M.5 composer-client cache-honesty step. M.0 started the same day.

## 12. Related docs

- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — master; §4 is this phase; §3 is the package
  contract M.2 enforces.
- `docs/plans/extension-e0-e2.md` — the system this marketplace serves; its §5 execution
  notes record what the E.2 proof fixed in the install chain.
- `apps/backend/extensions/baxela-sandbox-gateway/README.md` — the first ingestion
  candidate's own documentation.
