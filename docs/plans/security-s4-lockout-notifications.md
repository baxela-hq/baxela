# Plan S.4 — Admin sign-in lockout & new-device notifications

> **Purpose of this document:** Self-contained execution plan for Phase S.4
> of `docs/SECURITY_ROADMAP.md`, shaped by seven adversarial review rounds
> (rev 1–6) that hardened an initially simple design into a concurrency-
> correct one. **The standing invariants: a locked account never completes
> authentication — the failed-password path and the token-issuance boundary
> serialize against the SAME lockout-row lock; rejected attempts never
> extend the lock; device records are security history, never an
> authentication factor; `device_name` is a client-reported label, not
> verified identity.**
>
> **Created:** 2026-11-12 · **Revised:** 2026-11-12 — rev 1 (review,
> 8.7/10 — boundary bypass, lockout race, exception-based conflict
> handling, disableable security notification), rev 2 (9.2/10 — rehash
> lock inversion, lock lease, isolation wording), rev 3 (8.7/10 —
> row-lock boundary did not serialize against cache counters), rev 4
> (9.0/10 — DB-backed lockout state adopted; first-row bootstrap, clear
> semantics, window naming), rev 5 (9.3/10 — active-lock guard, clear
> placement, concurrent bootstrap coverage), rev 6 (9.5/10 — F26: caller
> must act on the clear outcome), rev 7 (this document — implemented);
> see §8 · **Owner:** solo maintainer · **Status:** implemented
>
> **How to use:** Conventional commits; never commit unless asked; heavy
> commands `nice -n 10`; Larastan + Pint before declaring done.
> **Budget: 0.5 d** (roadmap) — review-driven hardening added ~2 h.

---

## 1. Where this fits

Roadmap §5 assigns two controls to the single shared sign-in endpoint
(`POST api/v1/auth/public/auth/signin` — storefront customers AND admins):

| Gap (roadmap §1) | Control in this slice |
|---|---|
| "No account lockout on admin sign-in — a slow-drip brute-force runs indefinitely" | Account lockout after N failed attempts (config, default 5), locked M minutes (default 15); composes with the existing per-IP throttle. |
| "No login anomaly detection" | New-device sign-in email + in-app bell notification ("was this you?"), backed by persistent device memory. |

**Deliberate roadmap deviation:** §5 D1 said the lockout lives in cache.
Reviews proved a cache counter cannot serialize against token issuance
(different stores, different clocks), so the state moved into a DB table —
the failure path and the boundary hold the SAME row lock. Roadmap §5 is
annotated accordingly.

## 2. Verified state (2026-11-12)

| Fact | Evidence |
|---|---|
| One sign-in endpoint serves all audiences | `Modules/Auth/routes/api/public.php:19` — role-gating the lockout would leak which emails are admins (differential behavior) |
| Sign-out / forced revocation delete tokens | `SignOutAction` deletes the current PAT; `RevokeAllUserSessionsAction` deletes all — tokens alone cannot be device memory |
| SessionGuard rehashes on successful login | `SessionGuard:290` → `EloquentUserProvider::rehashPasswordIfRequired` (`EloquentUserProvider.php:169-178`) — a users-row UPDATE inside the gated attempt phase (F12) |
| `Lock::block` releases on throw (try/finally); no lease-renewal API | `Cache/Lock.php:130-136`, base `refresh()` throws |
| Dev cache `database` (has `cache_locks`), prod `redis` — both `LockProvider` | `0001_01_01_000001_create_cache_table.php:20`, `.env.production:29` |
| Notification pipeline supports database + email | `DatabaseChannel` (bell rows + Reverb broadcast), `EmailChannel`, `LockedNotificationCodes` consumed by dispatch filter + preference API + matrix |
| Locale captured at event time | `Locale::fromRequest()` (`Modules/Core/Utils/Locale.php`), house pattern `OrderGateway:146-149` |
| Laravel 13 `Arr::get` resolves flat dotted lang keys | literal-key fast path before dot navigation — `sign_in.locked` flat entries match the `two_factor.*` house style |
| Carbon 3 `diffInSeconds` is SIGNED | future unlock → negative diff — `retryAfter()` uses timestamp arithmetic |
| Bcrypt option key is `rounds` (not `cost`); minimum cost 4; the `hashed` cast rejects hashes whose cost exceeds the configured rounds | `BcryptHasher::cost()`, `HasAttributes:1517` (`Hash::verifyConfiguration`) |
| SQLite (tests) has no row locks — `lockForUpdate` degrades to whole-DB serialization; MySQL (prod/dev) has real row locks | concurrency tests are MySQL-gated and re-run against MySQL |
| 2FA `consume()` nulls the challenge context on the in-memory model | pre-existing bug surfaced by S.4: both 2FA actions read the context after consuming → the 2FA path always used `unknown-device` as the token name. Fixed: read context BEFORE consume |

## 3. Design decisions

### Lockout state and serialization

| # | Decision | Why |
|---|---|---|
| D1 | **Lockout state is a DB table** `auth_user_devices`-style row store `auth_sign_in_lockouts`, keyed by SHA-256 of the canonical email (raw addresses never hit rows or cache keys; unknown emails get rows — uniform behavior, no existence disclosure). | Reviews F2/F17: a cache counter cannot serialize against token issuance; the failure path and the boundary must share one synchronization point. |
| D2a | **Bootstrap-first row creation**: `upsert()` zero-row (atomic insert-if-absent — unique constraint arbitrates; only duplicate-key conflicts absorbed, unexpected errors propagate) → `lockForUpdate()->first()` (the row now exists ⇒ a true row lock) → null ⇒ throw. Never `SELECT ... FOR UPDATE` a possibly-missing row (InnoDB gap locks are not mutually exclusive) and never `INSERT IGNORE` (suppresses non-duplicate errors). | F19: a missing row cannot itself be row-locked. |
| D2e | **`recordFailure()` guard under the row lock**: if `locked_until > now()` — zero writes (not even `last_failed_at`; touching it would keep the counter fresh past expiry and re-lock on the first post-expiry failure), outcome `LockedWhileInFlight`; the caller answers 429 instead of the generic credentials error. | F23: two requests can both pass the pre-check before either establishes the lock. |
| D2f | **Counter clears at password verification** (`clearAttempts()` in the attempt phase, inside the email gate) — including when the user proceeds to 2FA; an abandoned/failed challenge leaves the counter cleared. The boundary never mutates the counter. | F24: earlier failures must not haunt a proven password. |
| D2h | **The caller acts on `clearAttempts()`'s outcome**: `LockedWhileInFlight` → throw 429 immediately — no 2FA challenge, no enrollment, no completion. | F26: the pre-check cannot survive a cache-lease expiry during password verification; a valid password is not permission to continue. |
| D2i | **Inactivity-reset window = `lockout_minutes` itself** (no separate key): the threshold failure sets `last_failed_at = T_lock` and `locked_until = T_lock + M`, so the counter is exactly window-stale at expiry — the account always gets N fresh attempts. Failures spaced *closer* than the window accumulate until lockout (named honestly: inactivity-reset, NOT a rolling window). | F21 + reviewer's configuration-clarity request. |
| D1-rev4 | **The per-email cache gate** (`Cache::lock('sign-in-gate:'.sha256, 30)->block(3)`) serializes only the attempt phase (pre-check + credential verification + counter writes). Lease-expiry overlap is a documented residual limitation (extra verifications, possible counter under-count) — the security invariant never depends on the lease. Acquisition timeout → **fail-closed 503** `auth.sign_in.busy` (reported to Sentry; a 429 would lie about lock state). | F2/F13. |
| D2-lock | **Lock order (acyclic)**: attempt path = email gate → lockout row; boundary = user row → lockout row; the rehash UPDATE = email gate → user row (one autocommit statement). The lockout-row lock is always last; nothing acquires the email gate while holding any DB lock. | F12: rehash-on-login made the old order a deadlock candidate. |

### New-device notification

| # | Decision | Why |
|---|---|---|
| D5 | **Device memory is `auth_user_devices`** (user_id FK cascade, device_name 100, first/last_seen_at, unique pair — the only index). Registration at `issueToken()` via `insertOrIgnore` + affected-rows; a 0-count is followed by an existence SELECT so a suppressed non-duplicate error surfaces instead of reading as "already seen". | Survives sign-out/revocation/expiry; transaction-safe on all three completion paths (F3); F4/D2d honesty. |
| D6 | `device_name` is a **client-reported label**: notification copy says a sign-in was *reported* from device "X" — never claims verified identity; device data never weakens password/2FA. | F4. |
| D7 | `AUTH_USER_NEW_DEVICE` is added to `LockedNotificationCodes` — dispatch-time preference filtering bypassed, preference API rejects disabling, matrix flags locked. | F8: the "was this you?" signal must never be disableable. |
| D8 | Locale captured on the event (`Locale::fromRequest()`); listener is `ShouldQueue` post-commit — delivery is best-effort; `NotificationService::dispatch()` has NO per-channel try/catch, so a channel failure fails the queued job (retries → `failed_jobs`); **delivery is not idempotent** — a retry after partial delivery can duplicate the bell row (documented, accepted; no pipeline redesign). | F10/F14. |

### Explicitly out of scope

GeoIP, browser fingerprinting, risk scoring, a device-management UI, audit-log mapping (the Audit module's documented policy excludes customer-triggered events), storefront changes, notification-pipeline redesign.

## 4. Implementation work items

| # | Item | File |
|---|---|---|
| W1 | Config `auth.lockout.{max_attempts,lockout_minutes}`; error codes `auth.sign_in.locked` (429, meta retry_after_seconds/minutes, `:minutes` message param) + `auth.sign_in.busy` (503); `AuthException` factories; lang keys | `Modules/Auth/config/config.php`, `app/Exceptions/{ErrorCodeEnum,AuthException}.php`, `lang/en/errors.php` |
| W2 | `SignInLockoutService` + `FailureOutcome` (canonicalize/hash, gate, assertNotLocked, recordFailure, clearAttempts, bootstrapForUpdate, isLockedRow, retryAfter, throwLocked) | `Modules/Auth/app/Support/SignInLockout/` (new) |
| W3 | `SignInAction` — gate { pre-check → attempt → recordFailure/clearAttempts with outcome handling }; attempt input stays raw (collation handles case, keys canonicalize) | `Modules/Auth/app/Actions/Public/Auth/SignInAction.php` |
| W4 | Lockout table (email_hash PK, failed_attempts, last_failed_at, locked_until/at + index) + `SignInLockoutSchema` + model | `Modules/Auth/database/migrations/2026_11_12_100001_*.php` (new) |
| W5 | Device table (unique (user_id, device_name)) + `UserDeviceSchema` + model | `Modules/Auth/database/migrations/2026_11_12_100002_*.php` (new) |
| W6 | Prune command (`auth:prune-sign-in-lockouts` — inert rows: lock past AND counter older than 7d) scheduled daily | `Modules/Auth/app/Console/Commands/PruneSignInLockoutsCommand.php` (new), `AuthServiceProvider` |
| W7 | `CompleteAuthenticationAction::issueToken()` — transaction: user-row lock → is_active → lockout bootstrap + check → device registration → one-token-per-device delete → createToken; `device_is_new` threaded to `dispatchEvents()` through `SignInAction`, `VerifyTwoFactorAction`, `CompleteTwoFactorEnrollmentAction` | `Modules/Auth/app/Actions/Public/Auth/CompleteAuthenticationAction.php` + two 2FA actions |
| W8 | Context read before `consume()` in both 2FA actions (pre-existing device-name bug) | `VerifyTwoFactorAction.php`, `CompleteTwoFactorEnrollmentAction.php` |
| W9 | `NewDeviceSignInEvent` (id, email, device_name, signed_in_at, locale) | `Modules/Core/app/Contracts/Events/Auth/` (new) |
| W10 | Notification: enum case, channel matrix `['database','email']`, `LockedNotificationCodes`, queued listener, explicit event mapping, blade templates (en/fa) + database-channel lang entries (en/fa) | `Modules/Notification/` (enum, config, Support, Listeners/Auth/NewDeviceSignIn/, Providers, resources, lang) |
| W11 | Admin: lockout toast keyed on `err.code === 'auth.sign_in.locked'` with defensive `retry_after_minutes` handling + generic fallback; keys in all 6 locales | `apps/admin/src/features/auth/sign-in/hooks/use-sign-in.ts`, `apps/admin/public/locales/*/shared/common.json` |
| W12 | Tests (§5) + `AUTH_LOCKOUT_MAX_ATTEMPTS=1000` pin | `Modules/Auth/tests/Feature/Public/Auth/{SignInLockoutTest,NewDeviceSignInNotificationTest,SignInLockoutConcurrencyTest}.php` (new), `phpunit.xml` |
| W13 | Roadmap ticks + deviation annotation | `docs/SECURITY_ROADMAP.md` |

**Touched: 11 new backend files (service, enum, 2 schemas, 2 models, 2 migrations, command, event, listener) + 3 new test files; 12 edited backend files; 1 hook + 6 locale files in admin; 2 docs.**

## 5. Test plan

Pest + `RefreshDatabase`; house style (`HelperTrait`, `expect(...)` chains, file-local helpers).

| # | Test | Proves |
|---|---|---|
| T1 | 2 failures → 3rd (correct password) → 429 `auth.sign_in.locked` + `retry_after_minutes` | Threshold + envelope |
| T2 | Unknown vs known email → identical 429 envelopes | D9 no disclosure |
| T3 | IP throttle (perMinute 2) → 3rd request `http.429` + `Retry-After`; counter holds exactly the 2 recorded failures | Controls compose; throttle fires before the action |
| T4 | Failures during an active lock leave `locked_until`/`locked_at`/`failed_attempts` unchanged | Fixed window — no extension |
| T5 | `travel(16 min)` after lock → sign-in succeeds | Expiry |
| T6 | Failures 5 min apart → locked; gap 16 min → counter resets to 1 | Inactivity-reset semantics (accumulation + reset) |
| T7 | Success → `failed_attempts=0`, `last_failed_at=null`; single later failure doesn't lock | D2f |
| T8 | 2FA-pending: counter cleared at challenge issuance; abandoned challenge + 1 failure → not locked | D2f incl. 2FA case |
| T9 | **F26 forced interleave** (`DB::listen` hook locks the row after the pre-check SELECT): correct password → 429, no token; enrolled user → no challenge row | A valid password cannot advance a locked flow |
| T10 | **F23**: `recordFailure` ×2 → lock; third call → `LockedWhileInFlight`, zero writes | Active-lock guard |
| T11 | F19 sequential bootstrap: single row per email; boundary zero-row for a never-failed signer | Atomic bootstrap |
| T12 | **F25 (MySQL-gated)**: two runtime PDO connections — conn 1 uncommitted bootstrap; conn 2 blocks (bounded `innodb_lock_wait_timeout`) → contention proven; after commit conn 2 completes on the same single row; second PDO ≠ first | Shared-serialization bootstrap race |
| T13 | Gate held manually → 503 `auth.sign_in.busy` | Fail-closed contention |
| T14 | **F1 boundary**: valid sign-in 2FA challenge + locked account → verify → 429; challenge NOT consumed; no token; no events. Same for forced enrollment | Locked account never completes, on every path |
| T15 | **F16**: cost-5 hash (DB-level write — the `hashed` cast rejects cost>config) → needsRehash → sign-in OK → rehashed to config cost | Rehash-under-gate behavior pinned |
| T16 | Prune: expired+stale and bootstrap rows deleted; locked/fresh kept | W6 |
| T17-T25 | Notification: first sign-in → 1 bell row + mail (title/body rendered); repeat device → none, `first_seen` preserved / `last_seen` updated; different device → fires; token deletion → silent; 2FA path fires; `Accept-Language: fa` → event locale `fa`; racing double registration → 1 row + 1 notification; `Queue::fake` → listener pushed wrapped in `CallQueuedListener`; disabled preference cannot mute | D5/D6/D7/D8 |

## 6. Verification (performed)

1. `sh scripts/test.sh Modules/Auth/tests/Feature/Public/Auth/{SignInLockoutTest,NewDeviceSignInNotificationTest}.php` (SQLite) — 29 passed.
2. `Modules/Auth/tests/Feature/Public/Auth/SignInLockoutConcurrencyTest.php` — the F25 test lives in its OWN file using `DatabaseMigrations` (migrate:fresh, no wrapper): `RefreshDatabase` wraps every test in a transaction, so connection 1's commit would only release a savepoint and cross-connection commits would be unobservable. MySQL-gated (`markTestSkipped` otherwise); **run it in its own pest invocation** (its `migrate:fresh` would disturb RefreshDatabase files run afterwards). Verified against MySQL: connection 2's bounded lock wait proves contention, connection 1 commits for real, connection 2 completes on the single row.
3. Same two suites against MySQL (`DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3308 DB_DATABASE=baxela_backend_test …` against the dev compose mysql on loopback:3308) — 29 passed. One-time environment grants were needed: `CREATE DATABASE baxela_backend_test` + `GRANT ALL ON baxela_backend_test.* TO 'baxela'@'%'` (the mysql entrypoint scopes the app user to its own database).
4. `sh scripts/test.sh Modules/Auth/tests Modules/Notification/tests` — 177 passed, 1 skipped (existing suites unbroken, incl. the 2FA suites covering the context-before-consume fix).
5. Full backend suite — 1273 passed, 1 skipped, 0 failures.
6. `composer analyse` — level 6, zero new errors after baseline regeneration (7828 absorbed; stable across two runs); `vendor/bin/pint` clean on all changed files; admin typecheck/build.
7. Framework facts the tests now pin: Carbon 3 `diffInSeconds` is signed (retryAfter uses timestamp arithmetic); the Bcrypt option key is `rounds`, minimum cost 4, and the `hashed` cast rejects hashes whose cost exceeds the configured rounds (the F16 fixture writes the outdated hash at the DB level); `Queue::assertPushed` sees queued listeners wrapped in `CallQueuedListener`.
8. Staging (deployment checklist): deterministic row-lock harness (locked-first → 429 no token; unlocked-first → 200 then lock), two-process bootstrap race, parallel mixed-burst stress test (explicitly non-deterministic; timestamp-equality caveat documented), manual smoke (5 wrong passwords → toast with minutes; fresh device → email; re-sign-in silent).

## 7. Deployment prerequisites

| Component | Requirement |
|---|---|
| Queue worker | Required — the notification listener is queued; without it neither channel is processed |
| SMTP | Required for the email channel |
| Database | Lockout + device tables ship as migrations; notification rows persist regardless of Reverb |
| Reverb | Only for real-time bell updates, not persistence |
| Scheduler | `schedule:work` runs in prod compose — the prune command needs no extra cron |
| Env (optional) | `AUTH_LOCKOUT_MAX_ATTEMPTS` (default 5), `AUTH_LOCKOUT_MINUTES` (default 15) |

Known trade-offs (documented, accepted): an attacker who knows an email can cause repeated 15-minute lockouts (bounded by the fixed window and IP throttling — the classic account-lockout DoS trade-off); device-name rotation by a password holder can re-notify; lease-expiry overlap under extreme load can admit a few extra verifications.

## 8. Review log

| Rev | Reviewer verdict | Key changes |
|---|---|---|
| 1 | 8.7/10 — architecture sound, not implementation-ready | F1 boundary gate, F2 concurrency semantics, F3 atomic registration, F8 locked codes, F4 device-name honesty, F5–F11 clarifications |
| 2 | 9.2/10 — architecture approved, 3 must-fix | F12 rehash lock inversion (vendor-verified), F13 lease semantics, F14 isolation wording correction |
| 3 | 8.7/10 — row lock ≠ cache serialization | F17: lockout state moved into the DB; F13 residual-limitation language; F15/F18 test corrections |
| 4 | 9.0/10 — direction right, 2 correctness gaps | F19 bootstrap-first, F20 clear-on-success restored, F21 window naming, F22 verification upgrades, migration timestamps |
| 5 | 9.3/10 — one flow gap + policy naming | F23 active-lock guard, F24 clear-on-valid-password policy named, F25 concurrent bootstrap coverage |
| 6 | 9.5/10 — F26 only | F26 caller acts on `clearAttempts()` outcome; inactivity-window configuration made explicit |
| 7 | implemented | All findings resolved; pre-existing 2FA context bug (consume-before-read) fixed as a side effect |
