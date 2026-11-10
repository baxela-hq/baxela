# Plan S.1 — Two-factor authentication on admin accounts (TOTP)

> **Purpose of this document:** Self-contained execution plan for Phase S.1
> of `docs/SECURITY_ROADMAP.md`. TOTP is a security control — every bypass
> path gets explicit design, and the tests target the bypass attempts, not
> just the happy path. **The standing principle from the reviewer: every
> guarantee must hold under concurrent requests, not merely sequentially.**
>
> **Created:** 2026-11-10 · **Revised:** 2026-11-10 — rev 2 folded the
> first review (7.8/10), rev 3 folds the second (8.8/10, five must-fix +
> six should-fix); see §12 · **Owner:** solo maintainer · **Status:** rev 3,
> awaiting the reviewer's final pass
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Slice
> strictly in order: **backend → frontend → policy**. Conventional commits;
> never commit unless asked; heavy commands `nice -n 10`; Larastan before
> Pest. **Budget: 3–4 d.**

---

## 1. Where this fits

The admin panel controls refunds, voids invoices, changes settings, and
manages payment methods. Today it's guarded by a single factor. This slice
adds TOTP as a second factor for **admin users** — opt-in per user, forced
for super-admins, with recovery codes and privileged account recovery.
Customer-facing 2FA and store-owner-configurable enforcement for regular
admins are S.1b, not this slice.

## 2. Verified state (2026-11-10)

| Fact | Evidence |
|---|---|
| Sign-in flow | `SignInAction::handle()` — guard attempt → verify → activate → device-token replace → `createToken(deviceName, ['*'], ttl)` → fires `UserSignedInEvent` (with cart token) → returns `{user, token}` |
| Token model | One token per device; opaque Sanctum tokens with per-device TTL from config |
| User model | `$fillable` = [email, email_verified_at, password, is_active, comment]; `$hidden` = [password, remember_token]; no 2FA columns |
| Admin sign-in surface | `apps/admin/src/features/auth/sign-in/` — form → `POST /auth/public/auth/signin` → zustand → staff gate → redirect |
| Admin routing | `admin.php` wraps everything in `auth:sanctum` + `PermissionMiddleware` (derives spatie permission from route name) — 2FA self-service must NOT land here |
| User routing | `user.php` wraps in `auth:sanctum` only — the right home for self-service 2FA management |
| Auth module DB prefix | `Module::DB_PREFIX` = `auth_` — the challenges table derives its name from this |
| Rate limiting | `throttle:auth-sign-in` (IP-based); no per-account limiter exists |
| No TOTP package | `composer.json` has no google2fa or similar |
| Storefront 2FA text | ⚠ `apps/storefront/components/account/settings.tsx` has a 2FA entry referring to email-based 2FA — needs reconciliation |
| SQLite tests | Tests run on `:memory:` SQLite — migrations must avoid MySQL-only syntax (the existing codebase uses Laravel's `$table->enum()` which works on both) |

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`pragmarx/google2fa`** (core TOTP library, no Laravel wrapper) + `bacon/bacon-qr-code` for the QR data URI. We control the integration because we need the matched time-step index for replay prevention. **Verify compatibility with Laravel 13 + PHP 8.3 before committing** (record the actual version). | The wrapper hides the time-step; we need it for D8. |
| D2 | **Four-column storage on `users`:** `two_factor_secret` (encrypted cast), `two_factor_recovery_codes` (text — JSON array of bcrypt hashes), `two_factor_confirmed_at` (timestamp), `two_factor_last_used_step` (bigint). All four are in `$hidden` — they never serialize. | Four columns; the fourth is the replay guard. |
| D3 | **Sign-in is two-step via a DB-backed challenge.** Password valid + 2FA confirmed → create a challenge row → return `{two_factor_required: true, challenge_token}`. Verify endpoint consumes the challenge and issues the normal token. The challenge carries an encrypted context JSON: `{device_name, remember, cart_token}`. **Context is an explicit allowlist** — nothing else can be stored; the verify endpoint never accepts replacement context. | The challenge carries sign-in context so verification preserves every existing behavior. |
| D4 | **Opaque token, SHA-256 hash PK.** The raw token is 32 cryptographically random bytes, hex-encoded (64 chars), shown to the client ONCE. The challenge table's PK is `token_hash CHAR(64)` — the SHA-256 of the raw token. The raw token is never stored. The client sends the raw token; the server hashes it and looks up the row. Input is validated as 64 hex chars before querying. | No UUID/hash contradiction; the hash-as-PK design prevents DB-identifier exposure and is uniformly opaque. |
| D5 | **Eight one-time recovery codes**, bcrypt-hashed per code, stored as a JSON array. **Consumption is atomic under a user-row lock**: inside a transaction, `lockForUpdate()` the user row, read the array, verify the submitted code against every hash without disclosing the position, remove only the matching hash, save, commit. Input is normalized (uppercase, trim, format detection `XXXX-XXXX` vs 6-digit). | Row-lock consumption guarantees single-use. No position disclosure. |
| D6 | **Challenge lifecycle: acquire → verify → consume (or fail).** The verify flow runs in ONE transaction: (1) begin; (2) lock the challenge row via the token hash; (3) validate: exists, `consumed_at IS NULL`, not expired, type matches the endpoint, failed_attempts < cap; (4) **lock the user row** (`lockForUpdate` — serializes TOTP/recovery acceptance across ALL challenges for this user, per the reviewer's cross-challenge finding); (5) verify the code (TOTP with replay guard D8, or recovery code D5); (6) if **invalid**: increment `failed_attempts` on the challenge, commit, return the error — the challenge stays alive for retry up to the cap; if **valid**: set `consumed_at = now()`, clear the encrypted context, commit, then issue the token. | The reviewer's critical finding: rev 2 consumed the challenge before verifying the code, destroying it on the first wrong attempt. The acquire-verify-consume split keeps the challenge alive for retries and consumes it only on success. The user-row lock in step 4 serializes replay protection across different challenges. |
| D7 | **Route placement: self-service 2FA under user routes** (`auth:sanctum`, no PermissionMiddleware). 2FA management is a self-service account feature, not a business permission. | Putting it under admin routes would accidentally require a spatie permission to manage your own security. |
| D8 | **TOTP replay prevention with user-row lock.** On successful verification, record the matched time-step index in `two_factor_last_used_step`. On subsequent verification (for ANY challenge belonging to the same user), reject any code whose matched time-step ≤ the stored value. The lock on the user row (D6 step 4) ensures the check-and-update is atomic across all concurrent challenges. The matched step comes from the TOTP library's verification result — not computed from the current time. Updated within the same transaction as the challenge consumption. | The reviewer's finding: two different challenges for the same user could both accept the same TOTP if only the challenge rows are locked. Locking the user row serializes acceptance. |
| D9 | **Two challenge types, strictly isolated, with an explicit state machine.** `sign_in` challenges authorize only `POST /2fa/verify`. `enrollment` challenges authorize only `POST /2fa/enrollment/{start,confirm}`. The type is on the challenge row and checked at consumption. **Enrollment state machine:** password verified → enrollment challenge issued → active (only enrollment ops) → started (secret generated, unconfirmed) → confirmed (2FA enabled, recovery codes returned, challenge consumed atomically with confirmation). **Failure states:** challenge expired (must re-authenticate), abandoned (unconfirmed secret remains unusable — it cannot authenticate), re-issued (new challenge generates a new secret, replacing any unconfirmed one; the previous challenge is atomically invalidated). **Active challenge limits:** max 3 concurrent `sign_in` challenges per user (supports independent multi-device sign-in), max 1 active `enrollment` challenge (a new one invalidates the previous). | The reviewer's finding: the enrollment flow needed precise states. The explicit table prevents ambiguity about what happens at each point. |
| D10 | **Layered rate limiting, atomically.** (a) Per-IP: 10/min via the existing `throttle:` middleware. (b) Per-challenge: max 5 failed attempts, stored as `failed_attempts` on the challenge row — the counter increments inside the same transaction as the failed verification (atomic by construction); reaching the cap invalidates the challenge (the next attempt finds `failed_attempts >= 5` and rejects). (c) Per-account: 10 failures / 15 min via `Cache::increment()` (atomic on Redis and file drivers — Laravel's cache abstraction handles this) with a fixed TTL; when the counter reaches 10, the ACCOUNT is blocked from BOTH password sign-in and 2FA verification for the remaining TTL; successful authentication resets the counter; the counter is per-instance (a multi-instance deployment would need a shared cache backend — documented as a deployment note). | The reviewer's finding: cache counters must be atomic. `Cache::increment()` provides this. The per-challenge cap is atomic by construction (inside the transaction). |
| D11 | **Disenrollment requires a valid TOTP or recovery code**, never just the password. Verified before the columns are cleared (same user-row lock transaction as D5). | A phished password can't silently kill the second factor. |
| D12 | **Privileged account recovery:** `POST /auth/user/2fa/admin-disable` with `{user_id, reason}` — requires `auth:sanctum` + super-admin check inside the action. Mandatory reason. Audit log records actor, target, reason, and timestamp. The action clears all four columns atomically and invalidates all active challenges for the target user. | The "user lost everything" escape hatch — must exist and be auditable. |
| D13 | **Scope: admin users only in S.1.** The backend is user-scoped and ready; the storefront customer enrollment UI is S.1b. The SECURITY_ROADMAP gets updated to match. | Honest scope. |
| D14 | **Secret lifecycle rules:** starting enrollment generates a new secret (replaces any unconfirmed one); an unconfirmed secret cannot authenticate; recovery codes exist only after confirmation; disable clears `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`, `two_factor_last_used_step`, and all active challenges — atomically; regeneration replaces all codes atomically (same user-row lock); the QR + secret response carries `Cache-Control: no-store`; the secret is never returned after confirmation; the secret, recovery-code hashes, and replay-tracking step are in `$hidden` — they never appear in serialized resources. Recovery codes are returned exactly once; the frontend shows an explicit "save these now" acknowledgement. | Explicit lifecycle, not implicit. |
| D15 | **Shared `CompleteAuthenticationAction`.** The device-token replacement, `createToken()` with the stored TTL, `UserSignedInEvent` dispatch (with cart token), and the `{user, token}` return shape are extracted from `SignInAction` into a dedicated action. Both `SignInAction` (non-2FA path) and `VerifyTwoFactorAction` (2FA path) call it. One authoritative implementation of the successful-authentication sequence; future changes can't be applied to one path and forgotten in the other. | The reviewer's recommendation. |
| D16 | **Sensitive context encryption.** The challenge's `context` JSON column uses an `encrypted` cast — the device name, remember flag, and cart token are never stored in plaintext. The context is cleared (nulled) when the challenge is consumed or expires (the scheduled pruner handles this). Device_name is truncated + sanitized before storage (same normalization as `SignInAction::deviceName()`). | The reviewer's finding: cart_token is a bearer credential for guest-cart access and must not sit in plaintext. |

## 4. Schema sketch (Laravel migration, per house conventions)

```php
// Modules/Auth/database/migrations/2026_11_11_000001_add_two_factor_to_users_table.php
Schema::table(UserSchema::TABLE, function (Blueprint $table) {
    $table->text(UserSchema::TWO_FACTOR_SECRET)->nullable();
    $table->text(UserSchema::TWO_FACTOR_RECOVERY_CODES)->nullable();
    $table->timestamp(UserSchema::TWO_FACTOR_CONFIRMED_AT)->nullable();
    $table->bigInteger(UserSchema::TWO_FACTOR_LAST_USED_STEP)->nullable();
});

// Modules/Auth/database/migrations/2026_11_11_000002_create_two_factor_challenges_table.php
Schema::create(TwoFactorChallengeSchema::TABLE, function (Blueprint $table) {
    // SHA-256 hash of the raw 64-char hex token — never the raw token
    $table->char(TwoFactorChallengeSchema::TOKEN_HASH, 64)->primary();
    $table->foreignId(TwoFactorChallengeSchema::USER_ID)
        ->constrained(UserSchema::TABLE)->restrictOnDelete();
    $table->string(TwoFactorChallengeSchema::TYPE); // sign_in | enrollment
    $table->text(TwoFactorChallengeSchema::CONTEXT)->nullable(); // encrypted cast
    $table->unsignedInteger(TwoFactorChallengeSchema::FAILED_ATTEMPTS)->default(0);
    $table->timestamp(TwoFactorChallengeSchema::EXPIRES_AT);
    $table->timestamp(TwoFactorChallengeSchema::CONSUMED_AT)->nullable();
    $table->timestamps();
    $table->index([TwoFactorChallengeSchema::USER_ID, TwoFactorChallengeSchema::TYPE]);
    $table->index(TwoFactorChallengeSchema::EXPIRES_AT);
});
```

Table name derives from `Module::DB_PREFIX` → `auth_two_factor_challenges`.
All names go through `Schema` constants (`UserSchema`, new
`TwoFactorChallengeSchema`). Uses `$table->string()` for the type
(Laravel's string maps to VARCHAR on both MySQL and SQLite — no raw ENUM).

## 5. API surface

### Public (challenge-token gated, rate-limited)

| Route | Challenge type | Body | Response |
|---|---|---|---|
| `POST /auth/public/auth/2fa/verify` | sign_in | `{challenge_token, code}` | `{user, token, settings}` |
| `POST /auth/public/auth/2fa/enrollment/start` | enrollment | — | `{secret, qr_code_uri}` |
| `POST /auth/public/auth/2fa/enrollment/confirm` | enrollment | `{code}` | `{recovery_codes: [...]}` |

### User routes (auth:sanctum, no PermissionMiddleware)

| Route | Body | Response |
|---|---|---|
| `GET /auth/user/2fa/status` | — | `{enrolled, confirmed_at, recovery_codes_remaining}` |
| `POST /auth/user/2fa/enable` | — | `{secret, qr_code_uri}` (no-store) |
| `POST /auth/user/2fa/confirm` | `{code}` | `{recovery_codes: [...]}` |
| `POST /auth/user/2fa/disable` | `{code}` | `{success: true}` |
| `POST /auth/user/2fa/recovery-codes` | `{code}` | `{recovery_codes: [...]}` |
| `POST /auth/user/2fa/admin-disable` | `{user_id, reason}` | `{success: true}` (super-admin only, audit-logged) |

## 6. Work items (~3–4 d)

### Backend (~2.5 d)

- [ ] Verify `pragmarx/google2fa` + `bacon/bacon-qr-code` compatibility with
      Laravel 13 + PHP 8.3; record the actual versions in the commit message.
- [ ] Migrations: four columns on users + the challenges table (per §4 schema,
      all names through Schema constants).
- [ ] `UserSchema` + `TwoFactorChallengeSchema` constants.
- [ ] `User` model: fillable, casts (`two_factor_secret` => encrypted,
      `two_factor_recovery_codes` => array, `two_factor_confirmed_at` => datetime,
      `two_factor_last_used_step` => integer), all four in `$hidden`.
- [ ] `TwoFactorChallenge` model: encrypted context cast, type constants,
      `active()` scope (unexpired + unconsumed + under attempt cap).
- [ ] `TwoFactorChallengeService`: `issue()` (generates the raw token, hashes
      it, stores the row, returns the raw token once), `resolve()` (validates
      format, finds by hash, locks the row), `recordFailure()`, `pruneExpired()`.
      Active challenge limits enforced at issue time (max 3 sign_in, max 1
      enrollment per user; new enrollment invalidates the previous one).
- [ ] `TwoFactorService`: `generateSecret()`, `qrCodeUri()`, `verifyCode()`
      (returns the matched time-step for replay tracking), `verifyAndConsumeRecoveryCode()`
      (atomic under user-row lock, no position disclosure, normalized input),
      `generateRecoveryCodes()`, `clearAll()`.
- [ ] `CompleteAuthenticationAction`: extracted from `SignInAction` —
      device-token replace, `createToken()` with context TTL,
      `UserSignedInEvent` with context cart token, `{user, token}` return.
- [ ] `SignInAction` change: after activation check, if 2FA confirmed → issue
      a sign_in challenge (with encrypted context) and return the challenge
      response; if super-admin + force + unconfirmed → issue an enrollment
      challenge. Otherwise → call `CompleteAuthenticationAction` directly.
- [ ] `VerifyTwoFactorAction` + controller + route: the full D6 transaction
      (lock challenge → validate → lock user → verify code → consume or
      increment failure → commit), then `CompleteAuthenticationAction`.
- [ ] Enrollment endpoints (public, enrollment-challenge-gated): start and
      confirm. Confirm consumes the challenge atomically with the confirmation.
- [ ] User self-service endpoints: status, enable, confirm, disable,
      regenerate recovery codes.
- [ ] Privileged recovery endpoint: super-admin check + mandatory reason +
      audit log + challenge invalidation.
- [ ] Rate limiters: per-IP `throttle:auth-2fa-verify` (10/min), per-challenge
      5-attempt cap (on the row), per-account `Cache::increment()` counter
      (10/15 min, blocks both password sign-in and verification).
- [ ] `auth_two_factor_challenges:prune` scheduled command (hourly; clears
      expired context, removes rows past retention).
- [ ] Error codes + lang ×6: `auth.two_factor.{invalid_code, challenge_expired,
      challenge_invalid, too_many_attempts, not_enrolled, already_enrolled,
      enrollment_required}` (7 codes, consistent count).
- [ ] Storefront reconciliation: audit `settings.tsx` for the existing email-2FA
      text; remove or correct it.

### Admin frontend (~1 d)

- [ ] Sign-in form: handle `{two_factor_required}` → OTP input → verify →
      store token → staff gate → redirect.
- [ ] Sign-in form: handle `{two_factor_enrollment_required}` → redirect to
      the enrollment wizard.
- [ ] Enrollment wizard: QR + secret → code input → confirm → recovery codes
      display with "save these now — they won't be shown again" acknowledgement.
- [ ] 2FA management section (profile settings): status, enable, disable
      (with code prompt), regenerate recovery codes.
- [ ] i18n keys ×6.

## 7. Acceptance tests

**Sign-in + verification (including behavior preservation):**
- [ ] 2FA enrolled → challenge response, no bearer token.
- [ ] Correct TOTP → normal `{user, token}`; challenge consumed.
- [ ] Wrong TOTP → 400; challenge stays alive (up to 5 attempts).
- [ ] 6th failed attempt → challenge invalidated.
- [ ] Recovery code → normal response; consumed atomically.
- [ ] Challenge expired → 400.
- [ ] Non-2FA user → sign-in unchanged.
- [ ] Sign-in response preserves: device-token replace, correct TTL
      (`remember=false` → short TTL), `UserSignedInEvent` with cart token,
      guest-cart merge.

**Forced enrollment:**
- [ ] Super-admin without 2FA → enrollment challenge, no token.
- [ ] Enrollment wizard via challenge: start → QR, confirm → recovery codes.
- [ ] Enrollment challenge consumed atomically with confirmation (concurrent
      double-confirm → one succeeds).
- [ ] Enrollment challenge expired → must re-authenticate.
- [ ] Unconfirmed secret abandoned → cannot authenticate (sign-in unaffected).

**Challenge isolation:**
- [ ] Sign-in challenge on enrollment endpoints → rejected.
- [ ] Enrollment challenge on verify endpoint → rejected.
- [ ] Enrollment challenge for normal API access → 401.
- [ ] New enrollment challenge invalidates the previous one.

**Concurrency (the reviewer's core standard):**
- [ ] Two concurrent verify requests with the same challenge → exactly one succeeds.
- [ ] Two concurrent requests with the same recovery code → exactly one succeeds.
- [ ] Same TOTP code submitted twice sequentially → second rejected (replay guard).
- [ ] Same TOTP code on two DIFFERENT challenges for the same user → second
      rejected (user-row lock serializes acceptance).
- [ ] Recovery-code regeneration racing with consumption → one wins cleanly.
- [ ] Enrollment confirmation racing with another confirmation → one succeeds.

**Rate limiting:**
- [ ] Per-challenge 5-attempt cap enforced.
- [ ] Per-account 10/15-min block triggered; blocks both password and verify.
- [ ] Successful authentication resets the per-account counter.

**Enrollment lifecycle:**
- [ ] Enable → QR + secret with `Cache-Control: no-store`.
- [ ] Secret stored encrypted (not plaintext in DB).
- [ ] Confirm with correct code → confirmed_at set; recovery codes once.
- [ ] Confirm with wrong code → 400; confirmed_at stays null.
- [ ] Unconfirmed secret cannot authenticate.
- [ ] Disable with correct code → all four columns + active challenges cleared atomically.
- [ ] Disable with wrong code → 400; 2FA stays active.
- [ ] Disable with just password → 400.
- [ ] Regenerate recovery codes → old codes invalid immediately (atomically).
- [ ] Secret, recovery-code hashes, and last_used_step never appear in
      serialized user resources.

**Privileged recovery:**
- [ ] Super-admin disables another user's 2FA → success, audit-logged,
      challenges invalidated.
- [ ] Non-super-admin → 403.
- [ ] Missing reason → 422.

**Data integrity:**
- [ ] User deleted → challenge rows cascade (or restrict, per the FK).
- [ ] Max 3 active sign_in challenges per user; 4th request invalidates the oldest.
- [ ] Max 1 active enrollment challenge; new one invalidates the previous.

## 8. Edge cases

| Scenario | Behavior |
|---|---|
| User loses device + all recovery codes | Super-admin force-disable (audit-logged) → re-enroll |
| Clock drift | ±1 code window; extreme drift requires re-enrollment |
| Concurrent sign-in attempts | Independent challenges (max 3 active); both can complete |
| 2FA enabled mid-session | Existing token valid until expiry; next sign-in gates |
| User closes recovery-code screen without saving | Codes are unrecoverable — the UI warns before dismissal |
| bcrypt verification latency | 8 hashes × ~100 ms = ~800 ms worst case; benchmark in tests and note in the plan if it exceeds 500 ms (parallel verification is the fallback) |

## 9. Explicit non-goals

Customer-facing 2FA UI (S.1b). Store-owner-configurable enforcement for
regular admins (S.1b). SMS. WebAuthn/hardware keys. Email one-time codes
as a second factor. A separate 2FA module (this lives in Auth).

## 10. Roadmap updates

`SECURITY_ROADMAP.md` S.1: D8 ("all users") → "admin users in S.1;
customers in S.1b"; the edge-case table's "only the latest challenge" →
"each challenge is independent (max 3 active per user)."

## 11. Suggested commits

1. `feat(backend/auth): totp two-factor backend with atomic challenges`
2. `feat(admin): two-factor sign-in flow and enrollment wizard`
3. `docs(monorepo): record s1 two-factor execution`

## 12. Review trail

- **Rev 1 (2026-11-10):** drafted from the security roadmap's S.1 outline.
- **Adversarial review 1 (2026-11-10, 7.8/10):** three critical (enrollment
  deadlock, non-atomic recovery codes, non-atomic challenges) + six important.
  All folded into rev 2.
- **Adversarial review 2 (2026-11-10, 8.8/10):** three critical — the UUID/
  SHA-256 PK contradiction (now: opaque 64-char hex token, SHA-256 hash as
  CHAR(64) PK, raw token never stored, input validated as hex before lookup);
  challenge consumed before code verification (now: acquire-verify-consume in
  one transaction, invalid codes increment the counter but keep the challenge
  alive); enrollment state machine under-specified (now: explicit state table,
  challenge consumed atomically with confirmation, abandoned = unconfirmed
  secret unusable). Six important — TOTP replay needs user-row lock not just
  challenge-row lock (D6 step 4 + D8); encrypt challenge context including
  cart_token (D16); atomic per-account rate limiter via Cache::increment()
  (D10); recovery-code operational details (D5 normalization, no position
  disclosure, UI warning); active challenge limits (D9: 3 sign_in / 1
  enrollment, prune scheduled); migration conventions (D4 uses Auth's
  DB_PREFIX, Schema constants, no raw ENUM). Plus: `CompleteAuthenticationAction`
  extraction (D15), encrypted cast + $hidden specified (D2/D14), and the
  full concurrency/lifecycle test matrix (§7 now ~35 cases). Architecture
  confirmed sound by the reviewer — no redesign requested.
