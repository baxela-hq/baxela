# Plan P — Marketplace phase 4: public frontend, submissions, webhook auto-sync (`baxela-marketplace`)

> **Purpose of this document:** Self-contained execution plan for roadmap Phase 4
> (`docs/EXTENSION_MARKETPLACE_ROADMAP.md` §4 "Marketplace frontend + submissions") — the
> public face of the marketplace: browse/detail pages with README rendering, GitHub OAuth
> sign-in and the submission flow with its automated validation pipeline, moderation states,
> and webhook-driven auto-re-sync. Written after the Phase 3 exit gate passed (2026-11-13).
> Like marketplace-phase3, this document stands alone: verified state, design decisions,
> build order, edge cases, tests — readable without conversation history.
>
> **Created:** 2026-11-13 · **Owner:** solo maintainer · **Status:** **PASSED — implemented
> and proof gate green 2026-11-13** (owner read + adversarial review pass 1 (7.5/10) folded
> in, §11; P.0–P.3 landed as `b3fee21`, `55f7fbc`, `97a8dbe`, `dfcffb0` + checklist
> `f775d2f`; suite 158/158, Larastan clean, Pint clean; live proof boxes 0–6 done — see
> §7; roadmap Phase 4 ticked). Deployment notes: run `queue:work` for submissions and
> webhook syncs; create the real repo webhook when the marketplace has a public URL
> (manual fallback documented in the panel); production `GITHUB_WEBHOOK_SECRET` is an
> owner action.
>
> **Execution order (strict):** **P.0 → P.1 → P.2 → P.3 → P.4**. Each slice lands green and
> committed before the next starts. Size estimates are initial, not commitments:
> P.0 ≈ 1 d, P.1 ≈ 1 d, P.2 ≈ 3 d, P.3 ≈ 1 d, P.4 ≈ 0.5 d (≈ 6.5 d total — raised by
> review pass 1).
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. This app is a SEPARATE
> plain Laravel repo (`baxela-marketplace`) — the monorepo's module conventions do NOT
> apply; plain Laravel conventions do. Planning lives in the monorepo (this file); code
> does not. Conventional commits apply per its own history. Never commit unless asked;
> heavy commands `nice -n 10`; Larastan before Pest; a green happy-path test is not a
> reliable engine — the tests below target rejection, impersonation, signature-failure,
> and drift paths, not just submit-then-approve.

---

## 1. Where this fits

| Slice | Content | Why this order |
|---|---|---|
| P.0 | Schema groundwork + ingestion groundwork (README capture, sync audit rows) | Everything below is pages, flows and jobs over these rows; no user-visible change |
| P.1 | Public frontend: browse/search + package detail with README rendering | The registry is already proven (M.5); the public read side is pure presentation over Phase 3 data |
| P.2 | GitHub OAuth + submission flow + automated validation pipeline + approve/reject | Submissions need sign-in; approval needs the panel and the synchronizer; webhook setup rides on approval |
| P.3 | GitHub webhook auto-re-sync + orphan detection + flags (auto re-flag + community reports) | Reuses `PackageSynchronizer` as-is; needs P.2's approved repos and token mechanics to be real |
| P.4 | Manual review checklist + end-to-end proof gate | The phase exit: a real submission through the real flow, webhook push → auto-ingest |

Not in this plan (Phase 5+ per the roadmap): admin-panel integration in the monorepo,
paid/licensed enforcement, GitLab sources, abandoned-package adoption flow, core-N
readiness dashboard, automated install smoke-tests in CI. §9 lists this plan's own
explicit non-goals.

## 2. Verified state register (2026-11-13)

Phase 3 state (all verified against the codebase survey of 2026-11-13):

- ✅ Milestones M.1–M.5 shipped and proven live; suite 83/83, Larastan clean, Pint clean.
  Head commit `aaa2eb7`; repo clean and pushed.
- ✅ Data model: `packages` (`name` unique — identity locked at first ingest, `source_repo`
  unique, `type` from `extra.baxela.type`, `description`, `status` enum
  `active|yanked`), `releases` (append-only, FK `restrictOnDelete`, `version` normalized,
  `source_tag`, `source_commit_sha`, `source_composer_json_hash`, `dist_*`, `metadata`
  json = validated composer.json snapshot, `status`, `download_count`, `published_at`),
  `audit_log` (`actor_email`, `action`, `subject`, `reason`, `meta`). **No README storage
  and no moderation states exist anywhere.**
- ✅ The `active|yanked` enums are load-bearing visibility predicates in
  `DistController`, `PackageCatalog`, `RegistryDocument`, `UpdateResolver`, and
  `PackageSynchronizer` hardcodes `'status' => 'active'` on create. **Adding
  moderation values to these enums is forbidden by this plan** — moderation lives on
  separate tables (§3, D1).
- ✅ Ingestion: `PackageSynchronizer::sync($repo)` is idempotent by commit-SHA, drift-safe,
  flushes the registry cache only when something ingested, and recovers interrupted
  publications by re-syncing. `SourceProvider` contract: `tags()`, `file()`,
  `downloadTarball()`; `ArchiveProcessor` already extracts and validates every entry under
  the `ingest.*` caps. **No Job classes exist yet** (`QUEUE_CONNECTION=database`, jobs
  table migrated, no worker used so far). **No locking exists** — a webhook sync racing a
  manual sync would hit the unique constraint as an unhandled `QueryException`.
- ✅ `AuditLog::ACTION_PACKAGE_SYNC` exists but is **never recorded** — syncs are currently
  invisible in the audit trail.
- ✅ Public read surface: `GET /api/packages` (search/filter, in-memory, no pagination,
  sorted by name), `POST /api/packages/updates` (state machine), `GET /packages.json` +
  `GET /dist/...` behind `EnsureRegistryToken` (the reserved auth seam). HTML routes:
  the back-office panel only; `/` redirects to panel login; `welcome.blade.php` is the
  untouched unrouted Laravel default.
- ✅ Panel: session auth via `Auth::attempt` on `marketplace:user`-provisioned users; yank/
  unyank with required reason + audit; no create/edit (data enters only through sync).
  Users table is stock Laravel (`name`, `email`, `password`, …) — **no GitHub identity
  columns, no admin flag**.
- ✅ Frontend stack: Vite + Tailwind 4 configured (`package.json`, `vite.config.js`, inputs
  `resources/css/app.css` + `resources/js/app.js`) but **no view uses `@vite`** — the panel
  uses inline CSS. No markdown library in composer.json (`composer/semver` is the only
  domain dep).
- ✅ Config: `registry.token` (auth seam), `registry.cache_ttl`, `ingest.*` limits
  (hardcoded defaults, not env-driven), `services.github.token` (optional PAT, present in
  the working `.env` but **missing from `.env.example`**). **No OAuth client keys and no
  webhook secret key exist.**
- ✅ `CACHE_STORE=database` — atomic locks (`Cache::lock`) are available for the per-repo
  sync mutex (§3 D7).
- ⚠ `ArchiveProcessor::process()` returns a `ProcessedArchive` summary and deletes the
  extraction tree in `finally`; it captures only `composer.json`'s content today, and it
  deliberately **leaves the rebuilt zip** at `ingest/{tempKey}.zip` on success — the P.0
  seam work (§3 D3, D7–D8) and the validator's mandatory cleanup (§5 D3) exist because of
  this.
- ⚠ GitHub OAuth facts to verify at implementation: authorize URL
  `https://github.com/login/oauth/authorize`, token exchange `POST
  https://github.com/login/oauth/access_token` (Accept: application/json), identity
  `GET /user`. Scope names used below (`read:user`, `user:email`, `public_repo`,
  `admin:repo_hook`) follow current GitHub docs — re-check when creating the OAuth app.
  The push-access check reads the `permissions` object of `GET /repos/{owner}/{repo}`
  **authenticated as the submitting user** — verify the object is present for public repos
  with the `public_repo` scope.
- ⚠ GitHub webhook facts to verify at implementation: signature header
  `X-Hub-Signature-256` = `sha256=` + hex HMAC-SHA256 over the **raw request body** keyed
  with the webhook secret; hook creation `POST /repos/{owner}/{repo}/hooks` with
  `config: {url, content_type: json, secret}`; a ping event is delivered on creation;
  422 if an identical hook exists.
- ⚠ league/commonmark current major must support PHP 8.3 (verify at P.1 install time;
  fallback: a dependency-free minimal renderer is NOT acceptable — GFM tables and links
  are required features).
- ⚠ The public catalog is served from memory over all active packages (fine for a small
  registry; a sort parameter is added in P.1, pagination stays a non-goal).
- ⚠ Owner actions, **implementation time only** (per the phase-3 pattern of owner-only
  credentials): create the GitHub OAuth app and set its client id/secret; generate
  `GITHUB_WEBHOOK_SECRET`; ensure the proof repo (`baxela-hq/baxela-sandbox-gateway`) is
  owned by an account that can create webhooks on it. Nothing happens before P.2/P.3 need
  them.

## 3. Slice P.0 — Schema & ingestion groundwork (~0.5 d)

### Design decisions

1. **Moderation lives on separate tables, never on the `active|yanked` enums.** The
   registry's visibility predicates must not learn about moderation. New tables:
   - `submissions`: `id`, `user_id` FK (the submitter), `source_repo` (`owner/repo`),
     `status` (`pending|approved|rejected` — string, not an enum case of the registry),
     `reason` (nullable — the written rejection reason, required on reject),
     `meta` (json — validation report, sync outcome, webhook setup outcome),
     `github_token` (nullable, **encrypted at rest** — the submission-scope OAuth token),
     `github_scopes` (nullable json), `reviewed_by` (nullable FK), `reviewed_at` (nullable),
     `timestamps`. App-level rules: at most one **open (pending)** submission per
     `source_repo`; a repo that already has a Package row (any state, incl. orphaned) is
     not submittable — adoption is Phase 7. A rejected repo may be resubmitted (new row;
     old rows are kept as history).
   - `package_flags`: `id`, `package_id` FK, `kind` (`auto_major_bump|auto_manifest_change|
     community_report`), `status` (`open|resolved`), `note` (nullable), `reported_by`
     (nullable FK — null = automated), `meta` (json), `timestamps`.
2. **`packages.orphaned_at` (nullable timestamp) instead of an `orphaned` status.**
   "Orphaned" (repo gone) is an *operational* state, not an offer-withdrawal: the releases
   stay offered (the registry is a promise — installed copies and fresh installs keep
   working), only auto-sync stops. Any third status added to the `active|yanked` enum would
   silently drop orphaned packages from the registry document and 404 their dists — wrong
   on both counts. Set when a sync of an existing package hits
   `SourceUnavailableException::repositoryMissing`; **cleared by the next successful sync**
   (repo restored); idempotent writes only (audit row on the transition, not on repeats).
3. **`releases.readme` (nullable text) — captured from the processed archive, not via a
   separate GitHub fetch.** The tarball already contains `README.md` at the package root
   (the contract requires it from P.2 on); extracting its bytes during archive processing
   adds zero API calls and guarantees the rendered README is exactly what ships in the zip
   ("what ships is what shows"). Stored raw markdown, capped at `ingest.max_readme_bytes`
   (default 65 536) — a larger README truncates (the sync report's row detail carries the
   notice) rather than failing the ingest. Past releases keep `readme = null` (detail page
   renders a "no README" state). **Seam (review 1):** `ProcessedArchive` gains
   `readmeRaw: ?string`, captured during the existing validated-entry walk — filenames
   matched case-sensitively (`README.md`, fallback `readme.md`; no other variants: a
   `.rst`/bare-`README` repo is a P.2 validation rejection, self-served). The
   extract-then-delete behavior of `ArchiveProcessor` is otherwise untouched.
4. **`users` gains `github_id` (unique, nullable), `github_login` (nullable),
   `is_admin` (bool, default false).** OAuth-created users are never admins; the panel
   gates on `is_admin`; `marketplace:user` upgrades/creates admins (command updated to
   always set `is_admin = true` and stated in its output).
5. **Syncs become visible in the audit trail.** Every completed `PackageSynchronizer::sync`
   writes the existing-but-unused `package.sync` audit action: actor is the trigger
   (`cli`, `panel:{email}`, `webhook:{repo}`), subject is the package name (or repo when
   no package row yet), `meta` carries the report counters (`ingested/unchanged/drift/
   skipped`). The synchronizer gains an `actor` parameter (default `cli`); the command and
   later jobs pass theirs.
6. Audit actions added this phase (constant list on the class like the existing ones):
   `package.sync`, `submission.approved`, `submission.rejected`, `package.orphaned`,
   `package.flag`, `flag.resolved`, `package.reported`.
7. **The per-repo sync mutex lives in `PackageSynchronizer::sync` itself** (review 1: a
   job-level `WithoutOverlapping` lock alone cannot close the phase-3 race — the CLI never
   takes it, so a webhook job racing `marketplace:sync` still crashed on the unique
   constraint). `sync()` acquires `Cache::lock("sync:{$repo}")` (TTL beyond the worst-case
   sync duration, released in `finally`) with a bounded wait; a loser gets a clear
   "another sync of this repo is already running" typed exception — never a silent partial
   sync, never a crash. CLI, queued jobs, and any panel affordance contend on one key;
   the P.3 job keeps `WithoutOverlapping` for queue-level dedup only.
8. **`GithubSourceProvider` gains an optional per-instance token override** (constructor
   param defaulting to `config('services.github.token')`). P.2's validation runs with the
   submitter's token without touching global config; the CLI keeps the server token.

### Work items / tests

- [x] Migrations: `submissions` (with the partial unique index `CREATE UNIQUE INDEX … ON
      submissions(source_repo) WHERE status = 'pending'` — raw statement, SQLite supports
      it; the app-level duplicate check stays as the friendly path), `package_flags`,
      `releases.readme`, `packages.orphaned_at`, `users.github_*` + `is_admin`; model
      classes with casts (`github_token` → `encrypted`); `marketplace:user` sets
      `is_admin`. *(done 2026-11-13 — the partial index proven under a deliberate
      double-insert; the encryption-at-rest assertion reads the raw DB value.)*
- [x] Ingestion: `ProcessedArchive.readmeRaw` capture (case-pinned filenames, cap +
      truncation notice in the report row detail) stored on the release row; synchronizer
      `actor` parameter; `package.sync` audit row on every completed sync; the per-repo
      mutex (D7); `GithubSourceProvider` per-instance token override (D8); the
      `AuditLog` class docblock documents pseudo-actors (`cli`, `webhook:{repo}` —
      review 1 nit; SQLite cannot alter the existing column comment).
      *(done 2026-11-13 — the mutex contention test holds the lock manually and asserts
      the typed busy failure; a different repo is unaffected.)*
- [x] Config: `ingest.max_readme_bytes`; `.env.example` gains the missing `GITHUB_TOKEN`
      plus placeholders for the P.2/P.3 keys (added as those slices land).
      *(done 2026-11-13.)*
- [x] Existing-suite maintenance (review 1, staggered by slice): `MarketplaceUserCommandTest`
      output assertions updated **now** (the command changes in P.0); `panelUser()`-style
      user fixtures gain `is_admin = true` when the gate lands (P.2); the `/`-redirect
      `ExampleTest` is replaced by the home-page smoke test when `/` changes (P.1).
      *(done 2026-11-13 — landed in their respective slices; the user-command test now
      asserts is_admin on both paths.)*
- [x] Tests: README extracted and stored (and truncated over cap); README absent → null,
      ingest still succeeds (legacy repos keep ingesting); case sensitivity (`readme.md`
      captured, `README.rst` not); sync writes exactly one `package.sync` audit row with
      the right actor and counters; re-sync (unchanged) still writes the audit row (it
      happened, even with nothing ingested); **mutex contention** — a sync holding the
      lock makes a second sync of the same repo fail with the typed busy exception (and a
      different repo is unaffected); `marketplace:user` output states admin status; OAuth
      user defaults to non-admin.
      *(done 2026-11-13 — suite 108/108 after P.0+P.1; one discovery: Pest loads ALL test
      files per suite run, so file-scope helpers are global — the duplicated fixture
      helpers were renamed.)*

## 4. Slice P.1 — Public frontend (~1 d)

### Design decisions

1. **Stack: public pages are the first consumers of the configured Vite + Tailwind 4
   pipeline** (`@vite` in a new public layout, `resources/js/app.js` boots nothing beyond
   the import). The panel keeps its zero-build inline CSS — it works, it is internal, and
   nothing regresses. Customer-facing pages get the styling pipeline the app already
   declares; no new dependency.
2. **Markdown rendering: `league/commonmark`** (GFM extension enabled: tables,
   striethrough, task lists, autolinks) behind a small `ReadmeRenderer` service, configured
   **safely by construction**: `html_input: strip` (raw HTML in READMEs is removed, not
   escaped — XSS input never reaches the page), `allow_unsafe_links: false`
   (`javascript:`/`data:` URLs neutralized). Rendered per request in v1 (cheap at this
   scale); caching by release id + content hash is a noted later optimization.
3. **Routes (web group — sessions + CSRF):**
   - `GET /` — home **and** browse: search box (`?q=` over name+description), type filter
     (chips from the distinct `packages.type` values), sort selector
     (`downloads` (default) | `name` | `newest`). `welcome.blade.php` is deleted; the
     `/` → panel-login redirect is removed (panel is reached at `/panel`, unchanged).
   - `GET /packages/{name}` — detail; `{name}` constrained to the package-contract regex
     (`vendor/baxela-slug`). Shows: description, type badge, latest active version,
     `compat.core` badge, total downloads (sum over **all** releases — historical truth),
     version table (active releases only: version, compat, published date, downloads),
     README of the latest active release (rendered), install command
     `baxela:install {name}` (the front door proven in M.5), source-repo link, and an
     **orphaned banner** when `orphaned_at` is set (releases stay listed — §3 D2).
     Implementation note (review 1): the `{name}` constraint carries a `/` inside a single
     segment — Laravel replaces the segment pattern wholesale, so the regex must be
     anchored `^…$`.
   - Both pages 404 (same shape) for: missing package, yanked package, package with zero
     active releases — exactly the catalog/registry omission rule.
4. **`PackageCatalog::rows` gains an optional sort parameter** (`name` default — the API
   response is unchanged; `downloads` = release-download sum desc; `recently_ingested` =
   max `published_at` desc — review 1: `published_at` is set at ingestion and the upstream
   tag date is never stored, so "newest" would lie; the option is named for what it
   measures). The `downloads` sort and the detail page's "total downloads" use the **same
   sum** (over all releases) — one definition everywhere. One read model serves API and
   pages; pagination stays out (§9).

### Work items / tests

- [x] `composer require league/commonmark` (+ config snippet); `ReadmeRenderer` with the
      safe-by-construction settings; public layout + `@vite` build verified once.
      *(done 2026-11-13 — commonmark ^2.10, CommonMarkCore + GFM extensions
      (html_input strip, unsafe links off); the first `@vite` build ran clean.)*
- [x] Home/browse page (search, type filter, three sorts); detail page (all elements
      above); routes (detail `{name}` constraint anchored `^…$`); `welcome.blade.php`
      removed. *(done 2026-11-13.)*
- [x] Tests: browse lists only public-visible packages (yanked hidden); search matches
      name and description; type filter works; all three sorts produce the expected order
      (fixtures inserted to defeat insertion-order reliance, incl. `0.0.10 > 0.0.9` in
      recently_ingested); detail renders README as HTML with **`<script>` stripped** (XSS test);
      detail shows version table, compat badge, install command, download total; yanked /
      all-yanked / missing package → 404; orphaned package → 200 with banner and versions
      intact; API `GET /api/packages` response byte-identical to before the sort
      parameter (regression guard).
      *(done 2026-11-13 — page tests render with `withoutVite()`; the API regression
      test pins the exact row-shape keys.)*

## 5. Slice P.2 — GitHub OAuth + submissions + validation (~2 d)

### Design decisions

1. **Hand-rolled OAuth, no Socialite.** The flow is two redirects and two API calls,
   fully `Http::fake`-testable, and the zero-dependency discipline (plain Blade over
   Filament in phase 3) applies: the marketplace already owns a GitHub HTTP client.
   Two distinct OAuth steps, because sign-in and submission are different acts with
   different trust:
   - **Sign-in** — scopes `read:user user:email` (identity only, no repo access):
     `GET /auth/github/redirect` (CSRF `state` in session) → `GET /auth/github/callback`
     (state checked, code exchanged, `GET /user` + `GET /user/emails`) → find-or-create
     the local user by `github_id` (email from the primary verified email, fallback
     `{login}@users.noreply.github.com`). **No token retained from sign-in.**
   - **Submission** — a second authorize round-trip requesting
     `public_repo admin:repo_hook`. **The full sequence, pinned (review 1):**
     (1) `POST /submissions` with the pasted URL — normalize, run the instant pre-checks
     (package exists / pending duplicate); pass → stash the repo in a **session flash**
     and redirect to the GitHub authorize URL (state in session); (2) the submission
     callback (`GET /auth/github/submit/callback`) re-runs the pre-checks (time passed),
     exchanges the code for the token, runs the ownership check with it (repo public +
     `permissions.push`), and on any failure **discards the token** and bounces back with
     the reason — the token never touches the session at all, it goes straight from the
     exchange response into the encrypted column; (3) success → pending row + queued
     validation. **Every submission needs its own authorize round-trip** (the token is
     deleted after approval/rejection — no token reuse across submissions). The
     submission-time `permissions.admin` value is recorded in `meta` (review 1: webhook
     creation needs repo **admin**, not just push — a push-but-not-admin org collaborator
     passes the gate and would otherwise discover that only at approval; the panel
     surfaces "webhook will need manual setup" beforehand). **Retention policy: deleted
     on rejection and deleted right after successful webhook setup at approval;** a setup
     failure keeps it so approval can retry, with the panel surfacing
     `webhook_setup_failed`. Long-lived third-party token storage is accepted only under
     these bounds (encrypted at rest, task-scoped, deleted as soon as its task ends).
2. **Submission flow (signed-in only):**
   - `GET /submissions/create` (paste a repo URL) → `POST /submissions`: normalize to
     `owner/repo` (reject anything else); instant rejections before any GitHub call:
     package with that `source_repo` already exists (any state) → "already published";
     an open pending submission for that repo exists → "already pending". Then the
     submission-scope token is obtained, and the **ownership check** runs: repo must be
     public (`private: false` on `GET /repos/{full}` — the registry only ingests public
     repos) and the submitting user must have **push access** (`permissions.push` on the
     same response, roadmap's "ownership proven via push-access check"). Any failure here
     rejects with a clear message; nothing is persisted on failed ownership checks.
   - Passing checks create the `pending` submission and queue `ValidateSubmissionJob`
     (database queue). The submitter's dashboard (`GET /my/submissions`) lists their
     submissions with status, rejection reason, and validation summary.
3. **The automated validation pipeline is a non-publishing dry run of the real ingestion,
   plus scanning.** `SubmissionValidator` reuses the Phase 3 building blocks —
   `VersionNormalizer`, `PackageContractValidator`, `ArchiveProcessor` under the same
   `ingest.*` caps — and publishes nothing. Checks, in order, each an **instant,
   self-served rejection** with the reason surfaced on the dashboard (the roadmap's
   "self-served rejections"): ≥1 stable semver tag; composer.json at the newest tag;
   the full package contract (name regex, `baxela-extension`, `extra.baxela.*`, license,
   `compat.core`, version agreement); **README.md present** at the repo root; archive
     passes `ArchiveProcessor` (an archive that ingestion would reject is rejected here);
     **secret scan**; **red-flag greps** (over the same validated-entry walk — review 1:
     `ArchiveProcessor` deletes the extraction tree in `finally`, so scanning rides the
     P.0 seam, not a second pass over files that no longer exist). **Name collisions are
     a validator check too (review 1):** a manifest `name` already ingested from a
     **different** repo is an instant rejection — otherwise an approved submission
     crashes the first sync on the `packages.name` unique constraint (one-name-one-repo-
     forever, enforced now at the second repo's entry). **No residue (review 1):** the
     downloaded tarball and the processed zip are deleted in `finally` (mirroring the
     synchronizer's compensating cleanup) — a rejected, failed, or rate-limited
     validation leaves nothing on disk. The validation report is stored in
     `submissions.meta`; a passing validation leaves the submission **pending** for the
     human review step (validation filters obvious failures — it is not the approval).
     **Transient failures are not rejections (review 1):** `RateLimitExhaustedException`
     → the job releases itself with a delay until `resetsAt`; source failures after
     retries (5xx, repo gone/private mid-flight) → the submission is rejected with a
     "repo unavailable" reason (resubmittable). Neither writes a quality-signal reason.
4. **Secret scan and red-flag greps** run over extracted text files (null-byte sniff
   skips binaries; per-file scan cap = `ingest.max_file_bytes`):
   - Secret patterns (config array `ingest.secret_patterns`, high-confidence fixed list):
     private-key PEM headers (`BEGIN {RSA,EC,OPENSSH,DSA,PGP} PRIVATE KEY`), provider
     token prefixes (`AKIA[0-9A-Z]{16}`, `ghp_`/`gho_`/`ghu_`/`ghs_` 36+ chars,
     `sk_live_`, `xox[baprs]-`, `AIza…`, `npm_…`). **Rejection reports the file path and
     the pattern NAME, never the matched value** — the marketplace must not become a
     secret exfiltration surface.
   - Red-flag greps (config array `ingest.red_flag_patterns`): `eval(`, `base64_decode(`,
     `shell_exec(`, `proc_open(`, `popen(`, `system(`, `passthru(` — PHP-targeted, since
     baxela extensions are PHP. Hard rejection per the roadmap; the escape hatch is
     "remove the construct and resubmit", not reviewer discretion.
5. **Approve/reject (panel, admins only) — new submissions screen:**
   - **Reject:** written reason required (3..1000 chars, the yank convention); audit
     `submission.rejected`; stored token deleted; submitter sees the reason.
   - **Approve:** **status-guarded server-side** (review 1: a double-submit is a no-op,
     not a second webhook attempt) → audit `submission.approved` → **webhook created on
     the repo** via the stored token (`POST /repos/{full}/hooks`, `push` events,
     marketplace URL + `GITHUB_WEBHOOK_SECRET`) → token deleted → **`SyncPackageJob`
     queued** (the first ingest happens now, not at the next push) → status `approved`,
     `reviewed_by/at` set. A 422 is treated as "already exists" **only after
     disambiguation** (review 1: GitHub also 422s invalid hook config) — `GET
     /repos/{full}/hooks` is listed and matched for the marketplace's callback URL;
     found → success, not found → failure. If webhook creation fails (403/401 — scope
     lost, token stale; or the disambiguated 422): the approval still stands,
     `meta.webhook_setup_failed` is recorded, the panel shows a warning, and the manual
     fallback (documented copy-paste hook config) applies; the queued sync still ingests
     the package.
   - Edge (documented, not engineered): approval followed by a sync that finds zero
     stable tags (repo changed between validation and approval) → submission stays
     `approved`, the sync outcome lands in `meta`, and the panel's sync affordance
     re-runs it. Recovery stays "sync again" (phase-3 policy).
6. **Panel access becomes `is_admin`-gated.** The existing auth check gains the admin
   assertion; OAuth users hitting panel routes are logged out-style redirected (403 for
   API-ish paths is unnecessary — panel-only).

### Work items / tests

- [x] OAuth: redirect/callback routes with state checking; user find-or-create; login
      retains no token; submission-scope authorize step with its own callback
      (`GET /auth/github/submit/callback`) following D1's pinned sequence.
      *(done 2026-11-13 — with the implementation note's adaptation: ONE callback
      serves both intents (GitHub's single-callback rule); the session state stash
      carries the intent.)*
- [x] Submit flow: URL normalization, pre-checks, ownership check, pending row, queued
      validation; `ValidateSubmissionJob`; `SubmissionValidator` (dry-run ingest +
      README presence + secret scan + red-flag greps); dashboard page.
      *(done 2026-11-13 — the scans ride the P.0 seam (an `inspectFile` callback on
      `ArchiveProcessor.process`); rate-limited validation returns a retry-later
      outcome (the job releases until reset — the release call needs a worker, so the
      DECISION is pinned by a validator-level test).)*
- [x] Panel: submissions screen (pending first), approve/reject actions per D5; admin
      gate on the whole panel. *(done 2026-11-13 — `panel-admin` middleware alias on
      all panel routes; OAuth users 403, provisioned admins pass.)*
- [x] Config/env: `services.github.client_id/client_secret/redirect`,
      `GITHUB_OAUTH_CLIENT_ID`, `GITHUB_OAUTH_CLIENT_SECRET` in `.env.example`; owner
      creates the OAuth app (callback URL) **now** (implementation-time owner action).
      *(done 2026-11-13 except the owner action — the local `.env` carries placeholders;
      the real OAuth app credentials are §10's owner action, pending.)*
- [x] Tests: redirect issues state + expected scope; callback with wrong/missing state
      rejected; new user created / existing user linked by `github_id`; OAuth user denied
      panel (403/redirect) while `marketplace:user` admin passes; submit requires auth;
      invalid URL rejected; existing package rejected; duplicate pending rejected (and
      the partial index holds under a deliberate double-insert); **stored token ≠
      plaintext in the DB row** (encryption-at-rest assertion — review 1); private repo
      rejected; no-push-access rejected (permissions forged in `Http::fake`);
      `permissions.admin` recorded in `meta`; validation: happy path → stays pending;
      missing README → instantly rejected with reason; `.rst`-only README → rejected;
      no semver tags → rejected; each contract violation → rejected with the validator's
      reason; **manifest name already owned by a different repo → rejected** (review 1);
      secret pattern → rejected, response/logs contain the **pattern name and file but
      not the secret value**; red-flag grep (`eval(` in a fixture) → rejected;
      **validation leaves no residue** (no tarball, no zip under `ingest/`);
      rate-limited validation → job released until reset, submission still pending;
      source-unavailable validation → rejected as "repo unavailable"; approve → expects
      hook creation + token deletion + queued sync + audit row; double-approve → no-op;
      approve with disambiguated hook-422 → success; approve with hook-403 → approved +
      warning meta + sync still queued; reject → reason required, audit row, token
      deleted, dashboard shows reason.
      *(done 2026-11-13 — 30 new tests across AuthFlowTest + SubmissionsTest; suite
      139/139 at the slice's exit.)*

## 6. Slice P.3 — Webhook auto-re-sync + flags (~1 d)

### Design decisions

1. **Receiving endpoint, fail-closed:** `POST /github/webhook`, registered stateless
   beside the api routes (no session, no CSRF — its own
   `VerifyWebhookSignature` middleware). If `GITHUB_WEBHOOK_SECRET` is unset the endpoint
   is **disabled** (404 — fail-closed, never an unsigned accept). Signature:
   `X-Hub-Signature-256` vs HMAC-SHA256 over the raw body, `hash_equals`, bare 403 on
   mismatch or missing header, secret never logged. Events: `ping` → 200;
   `push` → resolve `Package` by `source_repo == repository.full_name` → queue
   `SyncPackageJob` → 202; unknown repo → 200, silent (no existence oracle); any other
   event → 200 ignored. Yanked packages still re-sync (operational — the state may be
   lifted later; visibility is decided at serve time, not sync time).
2. **`SyncPackageJob`:** `WithoutOverlapping` per repo for queue-level dedup — the race
   itself is closed by P.0's mutex inside `PackageSynchronizer::sync` (review 1: a
   job-only lock cannot serialize against the CLI, which never takes it). Behavior: catch
   `RateLimitExhaustedException` → release the job until `resetsAt` (GitHub's own clock,
   never a blind retry); transient source errors → bounded retries;
   `repositoryMissing` on an existing package → **orphan it** (`orphaned_at` set once,
   audit `package.orphaned`); **any successful sync clears `orphaned_at`** (review 1:
   cleared when tags were fetched OK — a repo that returns without new tags must not stay
   orphaned forever); the `package.sync` audit row lands with actor `webhook:{repo}`
   (P.0 machinery).
3. **Auto re-flags (the roadmap's "major version bumps / manifest changes re-flag"):**
   after a sync that ingested releases, **one evaluation per sync** (review 1): max-old vs
   max-new major across the ingested set, and previously-newest vs newly-newest
   `compat.core` — **major bump** (new max major > old) → `package_flags` row
   `auto_major_bump`; **compat-promise change** → `auto_manifest_change` (old/new
   recorded in `meta`). **At most one open flag per kind per package** — an existing open
   flag is refreshed (meta updated), not duplicated; a restored repo cannot storm the
   flags screen. Identity drift is already rejected upstream by the contract validator.
   Flags are review signals only — **nothing hides automatically**; the admin decides
   (resolve, or yank via the existing `YankService`).
4. **Community report button:** on the detail page (signed-in users), `POST
   /packages/{name}/report` with an optional note (max 1000 chars — review 1 nit) →
   `package_flags` `community_report` row; one **open** report per user per package; audit
   `package.reported`. No throttling middleware (§9) — the per-user open-report rule is
   the hygiene here.
5. **Panel flags screen:** open flags first (kind, package, note/meta, age), resolve
   action (optional reason; audit `flag.resolved`; resolving never mutates the package —
   yank remains the explicit separate action). Approved submissions show a webhook
   status line (`meta.webhook_setup_failed` surfaced as a warning).

### Work items / tests

- [x] `VerifyWebhookSignature` middleware + route registration (stateless);
      `SyncPackageJob` with lock, rate-limit release, orphan handling, audit; re-flag
      detection; report endpoint + button; panel flags screen; config
      `services.github.webhook_secret` + `.env.example`; owner generates the secret
      (implementation-time owner action).
      *(done 2026-11-13 — a local secret was generated into the dev `.env` for the
      proof; the production secret stays §10's owner action. The `WithoutOverlapping`
      dedup from D2 was folded into the job's mutex release + `backoff` property: the
      race is closed by the synchronizer lock, so the job relies on release-on-busy.)*
- [x] Tests: signature — valid passes, missing/wrong header 403, tampered body 403, no
      secret configured → endpoint 404; ping 200; push for known repo queues exactly one
      job; push for unknown repo → 200 and nothing queued; yanked package push still
      queues; job: successful sync → audit row + cache flush (existing sync fakes),
      rate-limit → released until `resetsAt`, `repositoryMissing` → orphaned + audit,
      repeat missing → no duplicate audit, restored repo → `orphaned_at` cleared;
      major-bump flag created (and not for minor bumps); compat change flag created (and
      not for identical compat); report — unauthenticated rejected, one open per
      user+package, second attempt 422, panel screen lists and resolves with audit.
      *(done 2026-11-13 — 19 new tests; one adaptation: the rate-limit release decision
      is pinned at the validator/job boundary, release mechanics need a live worker.
      Suite 158/158 at the slice's exit.)*

## 7. Slice P.4 — Review checklist + proof gate (~0.5 d, the phase exit)

- [x] `docs/REVIEW_CHECKLIST.md` in the marketplace repo (the roadmap's manual review
      checklist): install cleanly in a disposable Baxela container; happy path for the
      extension's type (sandbox payment + webhook for drivers); read the webhook handler —
      signature verification actually enforced?; manifest honesty (declared vs shipped);
      secret-scan clean; the reviewer writes the outcome into the submission before
      approving. *(done 2026-11-13.)*
- [x] **Proof gate boxes (all through the front door, on the live app):**
  0. **Reset the registry:** `migrate:fresh` on the dev database — the sandbox is already
     ingested (M.5 state: 0.0.1 yanked, 0.0.2/0.0.3 active) and re-submitting an ingested
     repo is blocked **by design** (review 1); the empty registry makes it submittable,
     and re-ingest afterwards reproduces the M.5 state from upstream (the sandbox repo
     and its tags are live). The dev DB is disposable; the proof state is recorded here
     and in the phase-3 plan.
     *(done 2026-11-13 — and better than scripted: the M.5 state came back THROUGH the
     submission flow itself, not a CLI re-ingest.)*
  1. Sign in with GitHub via the real OAuth app; a non-admin account is created.
     *(done 2026-11-13 — identity scopes only; the OAuth user is non-admin, proven
     again by the 403 they then hit on `/panel` — the trust-domain gate working live.)*
  2. Submit `baxela-hq/baxela-sandbox-gateway` by pasting its URL; ownership (push
     access) verified; validation passes; submission is pending. *(A deliberately broken
     variant first — e.g. a repo with a red-flag pattern — is rejected instantly with
     reasons, proving the self-served pipeline.)*
     *(done 2026-11-13 — validation passed all six checks in 4 s via the queue worker.
     The broken-variant box ran in the test suite rather than against a live repo:
     SubmissionsTest pins the instant rejections — secret pattern (value never
     surfaced), red-flag grep, missing/`.rst`-only README, no-semver-tags, contract
     violations, cross-repo name collision — against real fixture archives.)*
  3. Owner reviews per the checklist, approves in the panel → webhook created on the
     repo, token deleted, first sync ingests, package appears; audit trail shows
     `submission.approved` + `package.sync`.
     *(done 2026-11-13 — approve → first sync ingested 3 releases; audit trail complete.
     The webhook install itself hit the DESIGNED failure path: the org's OAuth-app
     restrictions 403'd a token from the superseded personal app → `webhook_setup_failed`
     recorded, token custody enforced (deleted once the task ended), manual fallback
     shown in the panel. Root cause fixed for the future: the OAuth app is now
     ORG-OWNED (`Ov23liZ6GXLBJeO4fo70`), so real submissions install hooks cleanly at
     approval; the sandbox's hook is deferred to deployment (localhost cannot receive
     deliveries anyway).)*
  4. Tag `v0.0.4` upstream → the webhook fires → the release auto-ingests with no
     manual sync; `POST /api/packages/updates` offers `0.0.4`; the detail page shows the
     new version and the rendered README. **Delivery honesty:** on a localhost
     deployment, GitHub cannot deliver — the webhook is created (harmless) and the
     receiving pipeline is proven with a **faithfully signed push payload** (real
     secret, GitHub's documented payload shape) after the real upstream tag; a tunnel
     (cloudflared) is the stronger variant if available and is preferred when the
     marketplace runs on a public host.
     *(done 2026-11-13 with the sanctioned variant — `v0.0.4` pushed upstream for real
     (composer version bumped to keep the agreement rule); a locally-signed push
     delivery answered 202, the worker auto-ingested v0.0.4 in 3 s, and the registry
     document, detail page, and updates endpoint (`update_available`, latest `0.0.4`)
     all saw it with zero manual sync; audit actor `webhook:baxela-hq/baxela-sandbox-gateway`.)*
  5. Community report on the package → flag appears; admin resolves it; audit rows
     complete.
     *(done 2026-11-13 — `package.reported` → flag → `flag.resolved`, all audited.)*
  6. Orphan drill: point a test package's `source_repo` at a deleted repo → webhook push
     → `orphaned_at` set, banner visible, **releases still downloadable**; restore the
     mapping → the next successful sync (tags fetched, even with no new releases) clears
     it. **Scope note (review 1):** this mutates `source_repo`, which the identity policy
     (phase 3 §9.2) forbids everywhere except here — the drill runs on the box-0 reset
     dev DB only, never on a real registry.
     *(done 2026-11-13 — on the sandbox package itself, on the reset dev DB: orphan set
     once, banner live, dist 0.0.4 still downloading (200), registry document still
     offering, mapping restored → the next successful sync (0 ingested, 4 unchanged)
     cleared the orphan — the review-1 rule proven live.)*
- [x] All boxes green → tick roadmap Phase 4. *(ticked 2026-11-13.)*

**Phase exit: PASSED 2026-11-13.** Roadmap Phase 4 ticked; the marketplace has a public
face, a self-served submission pipeline, and hands-off re-sync — ready for Phase 5
(admin-panel integration) and the first production extension's publish through the real
flow.

## 8. Policies this plan adds

1. **Moderation is metadata, never a registry status.** Submission/flag tables carry the
   human workflow; `active|yanked` remain the only package/release states, and the
   append-only + yank policy (phase 3 §9) is untouched.
2. **Orphaned is operational, not an offer-withdrawal.** Releases of an orphaned package
   stay offered and installable; only auto-sync stops, and a banner says so.
3. **What ships is what shows.** README is extracted from the ingested archive, not
   fetched separately; markdown is rendered as data (HTML stripped) — the marketplace
   never renders author HTML.
4. **The marketplace never executes extension code** (phase-3 policy extended): validation
   is parsing, hashing, and grepping under the same caps as ingestion — nothing is
   included, booted, or eval'd, including for scanning.
5. **Minimum-viable token custody.** No long-lived third-party token storage: tokens are
   encrypted at rest, task-scoped, deleted on rejection and after webhook setup. The
   sign-in token is never retained at all.
6. **OAuth users are not admins.** The panel stays provisioned-only
   (`marketplace:user`); the public site and the panel are separate trust domains.
7. **The webhook endpoint is fail-closed.** No secret configured → disabled; bad
   signature → bare 403; unknown repo → silent 200 (no existence oracle).
8. **Automated rejection is self-served and explainable.** Every automated rejection
   states its reason and (for scans) the file and pattern name — never the matched
   secret value.

## 9. Explicit non-goals (silence reads as oversight)

Public catalog pagination, search beyond SQL LIKE, ETag/Last-Modified on registry routes
(explicitly re-deferred from the M.1 note — the 60 s document cache suffices until real
traffic says otherwise), GitLab sources, paid/licensed enforcement, API rate limiting as a
feature (only the per-user open-report rule exists), submission editing/re-submission UX
beyond "create a new submission", private-repo submissions, orphan recovery/adoption flow
(Phase 7), email notifications on submission state changes (the dashboard is the surface),
major-version changelog extraction, download analytics beyond counters, i18n, automated
install smoke-tests in CI, core-N readiness dashboard.

## 10. Owner actions (implementation-time only)

1. Create the GitHub OAuth app (callback URL per the deployed host) → set
   `GITHUB_OAUTH_CLIENT_ID` / `GITHUB_OAUTH_CLIENT_SECRET`. *(Before P.2 testing.)*
2. Generate `GITHUB_WEBHOOK_SECRET` and set it in the marketplace env. *(Before P.3
   testing.)*
3. For the P.4 proof: sign in with a real GitHub account that has push access to
   `baxela-hq/baxela-sandbox-gateway`; if full-fidelity webhook delivery is wanted on a
   public host, arrange reachability (tunnel or deployment) — otherwise the signed-payload
   variant in §7 is the sanctioned proof.
4. A deployment note the plan records once the proof passes: the database queue needs a
   running worker (`queue:work`) for submissions and webhook syncs — local proof runs it
   in a terminal; production deployment owns a real supervisor.

## 11. Review trail

- 2026-11-13 — drafted from roadmap §4 + the phase-3 exit state + a full codebase survey
  (schema, routes, services, ingestion, panel, tests, config). Awaiting owner read and
  the external adversarial pass; implementation starts only after both.
- 2026-11-13 — owner read + external adversarial review pass 1 (**7.5/10 — revision
  required**; the verified-state register and the policy compliance survived line-by-line
  checking; all findings folded in): **P1s** — the proof gate was unrunnable against the
  live registry (the sandbox is already ingested and re-submission is blocked by design →
  box 0 reset added); the job-level mutex could not close the webhook-vs-CLI race (lock
  moved into `PackageSynchronizer::sync`, §3 D7); cross-repo composer-name collisions
  crashed approved submissions at first sync (validator check added, §5 D3); the dry-run
  seam was asserted not designed (`readmeRaw` on `ProcessedArchive`, scan rides the
  validated-entry walk, per-instance provider token, mandatory tarball/zip cleanup,
  no-residue test — §3 D3, §5 D3); the orphan-clear rule contradicted itself (pinned:
  cleared on any successful sync, §6 D2). **P2s folded:** two-phase OAuth sequence pinned
  (token never in session, per-submission authorize, named callback, §5 D1);
  `permissions.admin` recorded + surfaced pre-approval (webhooks need repo admin, not
  just push); 422 disambiguated via the hooks listing; partial unique index for pending
  submissions; existing-suite maintenance staggered per slice; `ValidateSubmissionJob`
  transient semantics pinned (rate-limit release, "repo unavailable" rejection);
  encryption-at-rest assertion; auto-flag dedupe (one open flag per kind, one evaluation
  per sync); `newest` → `recently_ingested` (`published_at` is ingestion time); estimates
  raised (P.0 ≈ 1 d, P.2 ≈ 3 d, total ≈ 6.5 d); orphan drill scoped to the reset dev DB.
  **Nits folded:** README filename case-pinning, `actor_email` pseudo-actor docblock,
  approve idempotence guard, report note cap, downloads-sum consistency, anchored route
  constraint. Green light for implementation.
- 2026-11-14 — Zarinpal removed from the roadmap (owner decision: Phase 6 tombstoned,
  Phase 7 numbering kept so the paid-auth/GitLab references stay valid). Panel stack
  re-decided: the marketplace back office STAYS plain Blade — Filament only as a future
  wholesale-replacement slice, triggered by sustained review volume or Phase 7's
  paid-era administration, with the Filament × L13 compat check done first.
- 2026-11-13 — **implementation note (P.2):** GitHub OAuth apps register exactly ONE
  callback URL, and the plan's separate `/auth/github/submit/callback` would be rejected
  as a non-subset of it. Reality: ONE callback (`/auth/github/callback`) serves both
  intents; the session's state stash carries the intent (`login` | `submit`). The D1
  sequence is otherwise implemented verbatim.

## 12. Related docs

- `docs/EXTENSION_MARKETPLACE_ROADMAP.md` — master; §4 is this phase; §3 is the package
  contract the validation pipeline enforces.
- `docs/plans/marketplace-phase3.md` — the registry this phase exposes; its §9 policies
  carry over and extend.
- `docs/plans/extension-e0-e2.md` — the system the marketplace serves (`baxela:install`
  is the install command shown on detail pages).
