# Plan S.1 — Two-factor authentication on admin accounts (TOTP)

> **Purpose of this document:** Self-contained execution plan for Phase S.1
> of `docs/SECURITY_ROADMAP.md`. Like the gap and extension plans, this
> document stands alone: verified state, design decisions, work items,
> edge cases, and the acceptance-test list. TOTP is a security control —
> every bypass path gets explicit design, and the tests target the bypass
> attempts, not just the happy path.
>
> **Created:** 2026-11-10 · **Revised:** 2026-11-10 after adversarial review
> (7.8/10 — three critical findings and six important findings folded in;
> see §12 for the trail) · **Owner:** solo maintainer · **Status:** rev 2,
> awaiting the reviewer's second pass
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Slice
> strictly in order: **backend → frontend → policy**. Conventional commits;
> never commit unless asked; heavy commands `nice -n 10`; Larastan before
> Pest. **Budget: 3–4 d** (review-calibrated; the concurrency guarantees
> and enrollment-challenge separation are real work, not padding).

---

## 1. Where this fits

The admin panel controls refunds, voids invoices, changes settings, and
manages payment methods. Today it's guarded by a single factor (password +
rate limiting). This slice adds TOTP (time-based one-time passwords,
RFC 6238) as a second factor for **admin users** — opt-in per user, forced
for super-admins, with recovery codes and privileged account recovery.
Customer-facing 2FA and store-owner-configurable enforcement for regular
admins are explicit follow-ups (S.1b), not in this slice.

## 2. Verified state (2026-11-10)

| Fact | Evidence |
|---|---|
| Sign-in flow | `SignInAction::handle()` — `Auth::guard('user_session')->attempt()` → verify → activate → device-token replace → `createToken(deviceName, ['*'], ttl)` → fires `UserSignedInEvent` (with cart token) → returns `{user, token}` |
| Token model | One token per device (old token for same device deleted on re-login); opaque Sanctum tokens with per-device TTL from config (`auth.token_ttl.days` / `short_days` via `remember`) |
| User model | `User::$fillable` = [email, email_verified_at, password, is_active, comment]; `$hidden` = [password, remember_token]; no 2FA columns |
| Admin sign-in surface | `apps/admin/src/features/auth/sign-in/` — React form → `POST /auth/public/auth/signin` → stores token in zustand → `fetchAdminAccount()` (staff gate) → redirect |
| Admin routing | `Modules/Auth/routes/api/admin.php` wraps **everything** in `auth:sanctum` + `PermissionMiddleware` (derives spatie permission from route name `api.auth.admin.{resource}.{action}`) — 2FA self-service must NOT land here (D7) |
| User routing | `Modules/Auth/routes/api/user.php` wraps in `auth:sanctum` only — no PermissionMiddleware — the right home for self-service 2FA management |
| Rate limiting | `throttle:auth-sign-in` on the sign-in route (IP-based); no per-account or per-challenge limiter exists |
| No TOTP package | `composer.json` has no google2fa, otphp, or similar |
| Storefront 2FA text | ⚠ `apps/storefront/components/account/settings.tsx` has a 2FA settings entry referring to email-based 2FA — needs reconciliation (work item) |
| Super-admin bypass | `Gate::before` on the super-admin role bypasses permission checks — making super-admin protection especially important (reviewer confirmed) |

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`pragmarx/google2fa`** (the core TOTP library; NOT the `google2fa-laravel` wrapper — we handle the integration ourselves to control the flow). Plus `bacon/bacon-qr-code` for the QR data URI. Both pure PHP, shared-hosting safe. | We need the matched time-step index for replay prevention (D8) — the Laravel wrapper hides it. |
| D2 | **Four-column storage on `users`:** `two_factor_secret` (encrypted, nullable), `two_factor_recovery_codes` (text — JSON array of bcrypt hashes, nullable), `two_factor_confirmed_at` (timestamp, nullable), `two_factor_last_used_step` (int, nullable — TOTP replay tracking). | Four columns keep the model honest; the fourth is the replay guard the reviewer required. |
| D3 | **Sign-in is two-step via a DB-backed challenge.** Password valid + 2FA confirmed → create a `two_factor_challenges` row (uuid, user_id, type=`sign_in`, context JSON, expires_at, consumed_at, failed_attempts) → respond `{two_factor_required: true, challenge_token: "<uuid>"}` with no bearer token. Verify endpoint consumes the challenge atomically (D6) and issues the normal token. | The challenge carries the sign-in context (device_name, remember, X-Cart-Token) so the verify endpoint preserves every existing behavior (device-token replace, TTL, cart merge, UserSignedInEvent). |
| D4 | **Challenge table, not cache.** `two_factor_challenges`: `id` (uuid PK), `user_id` FK, `type` (`sign_in` \| `enrollment`), `context` (json — device_name, remember, cart_token), `expires_at`, `consumed_at` (nullable), `failed_attempts` (int, default 0), timestamps. Atomic consumption via `WHERE id = ? AND consumed_at IS NULL` inside a transaction with `lockForUpdate()`. Raw token never stored — a SHA-256 hash is the lookup key. | The reviewer's finding: cache read-then-delete is not atomic. A DB row with an atomic CAS-style update guarantees single-use under concurrency. Shared-hosting safe (no Redis dependency). |
| D5 | **Eight one-time recovery codes**, bcrypt-hashed per code, stored as a JSON array. **Consumption is atomic**: inside a transaction, `lockForUpdate()` the user row, read the array, verify, remove the matching hash, save, commit. Regeneration replaces the array atomically (same transaction). | The reviewer's finding: two concurrent requests loading the same array can both succeed. Row-lock consumption guarantees single-use. |
| D6 | **Challenge-token atomic consumption.** `TwoFactorChallengeService::consume()`: `DB::transaction(function () { $row = Challenge::where('id', $uuid)->whereNull('consumed_at')->lockForUpdate()->first(); if (!$row || expired) return null; $row->update(['consumed_at' => now()]); return $row; })`. One request wins; the other gets null. | Same concurrency guarantee as D5, same mechanism. |
| D7 | **Route placement: self-service 2FA lives under user routes, NOT admin routes.** The `user.php` group has `auth:sanctum` without PermissionMiddleware — 2FA management is a self-service account feature, not a business permission. No spatie permission is needed to manage your own second factor. | The reviewer's finding: admin routes derive permissions from route names; putting 2FA there would accidentally require a business permission to manage your own security. |
| D8 | **TOTP replay prevention:** on successful verification, record the matched time-step index (`two_factor_last_used_step` column). On subsequent verification, reject any code whose time-step ≤ the recorded value. The time-step comes from the TOTP library's verification result (not computed from the current time — the ±1 tolerance means the matched step may be the previous one). Updated atomically within the same transaction as the challenge consumption. | The reviewer's finding: a valid code can be replayed within its 30-second window. This is particularly important for Baxela's financial admin surface. |
| D9 | **Two challenge types, strictly isolated.** `sign_in` challenges can only be used on `POST /auth/public/auth/2fa/verify`. `enrollment` challenges can only be used on `POST /auth/public/auth/2fa/enrollment/{start,confirm}`. The type is stored on the challenge row and checked at consumption — a sign-in challenge cannot be used to enroll, and vice versa. Enrollment challenges are issued when a super-admin without confirmed 2FA signs in (the forced-enrollment path) and authorize ONLY the two enrollment endpoints — never normal API access. | The reviewer's critical finding: D6 (admin-only enrollment) contradicted D7 (forced enrollment without a token). The enrollment challenge solves the deadlock without ever issuing a bearer token before the second factor is active. |
| D10 | **Layered rate limiting on verification:** per-IP (10/min via the existing `throttle:` middleware), per-challenge (max 5 failed attempts → challenge invalidated, stored as the `failed_attempts` counter on the challenge row), per-account (10 failures / 15 min via a cache counter → the account gets a temporary sign-in block, same as the existing OTP lockout pattern). The verify route gets its own named limiter, separate from `auth-sign-in`. | The reviewer's finding: IP-only rate limiting is insufficient for a 6-digit OTP. Three layers compose: an attacker with many IPs still hits the per-account limit; a distributed attack against one account still triggers it. |
| D11 | **Disenrollment requires a valid TOTP code or recovery code**, never just the password. The code is verified before the columns are cleared (same atomic transaction as D5). | A phished password can't silently kill the second factor. |
| D12 | **Privileged account recovery:** `POST /auth/user/2fa/admin-disable` with `{user_id, reason}` — requires `auth:sanctum` + the super-admin role (not just any admin). Mandatory reason, audit-logged (actor, target, reason). This is the "user lost device + all recovery codes" path. It lives under the user route group (self-service convention) but is gated by a super-admin check inside the action. | The reviewer's finding: the edge case was mentioned but never designed. This is the operational escape hatch — it must exist and must be auditable. |
| D13 | **Scope: admin users only in S.1.** D8 from rev 1 ("all users can opt in") is deferred to S.1b — the backend is user-scoped and ready, but the storefront UI, customer enrollment flow, and the existing storefront 2FA text reconciliation are their own slice. The `SECURITY_ROADMAP.md` D8 note gets updated to match. | The reviewer's finding: the plan said "all users" but only built admin UI. Scope honestly. |
| D14 | **Secret lifecycle rules (from the review):** starting enrollment generates a new secret (replaces any unconfirmed one); an unconfirmed secret cannot authenticate; recovery codes exist only after confirmation; disable clears all four columns atomically; regeneration replaces all codes atomically; the QR + secret response carries `Cache-Control: no-store`; the secret is never returned after confirmation; the secret never appears in serialized user resources. | Explicit lifecycle, not implicit assumptions. |

## 4. Schema sketch

```sql
ALTER TABLE users
  ADD COLUMN two_factor_secret TEXT NULL,
  ADD COLUMN two_factor_recovery_codes TEXT NULL,
  ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL,
  ADD COLUMN two_factor_last_used_step BIGINT NULL;

CREATE TABLE auth_two_factor_challenges (
  id CHAR(36) PRIMARY KEY,           -- the SHA-256 hash of the raw token is the PK
  user_id BIGINT NOT NULL,
  type ENUM('sign_in', 'enrollment') NOT NULL,
  context JSON NULL,                  -- {device_name, remember, cart_token}
  failed_attempts INT NOT NULL DEFAULT 0,
  expires_at TIMESTAMP NOT NULL,
  consumed_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  INDEX (user_id, type),
  INDEX (expires_at)
);
```

## 5. API surface

### Public (no auth — challenge-token gated)

| Route | Auth | Body | Response |
|---|---|---|---|
| `POST /auth/public/auth/2fa/verify` | sign_in challenge | `{challenge_token, code}` | `{user, token, settings}` or error |
| `POST /auth/public/auth/2fa/enrollment/start` | enrollment challenge | — | `{secret, qr_code_uri}` |
| `POST /auth/public/auth/2fa/enrollment/confirm` | enrollment challenge | `{code}` | `{recovery_codes: [...]}` |

### User routes (auth:sanctum, no PermissionMiddleware)

| Route | Body | Response |
|---|---|---|
| `GET /auth/user/2fa/status` | — | `{enrolled, confirmed_at, recovery_codes_remaining}` |
| `POST /auth/user/2fa/enable` | — | `{secret, qr_code_uri}` (no-store) |
| `POST /auth/user/2fa/confirm` | `{code}` | `{recovery_codes: [...]}` |
| `POST /auth/user/2fa/disable` | `{code}` | `{success: true}` |
| `POST /auth/user/2fa/recovery-codes` | `{code}` | `{recovery_codes: [...]}` |

### Privileged recovery

| Route | Auth | Body | Response |
|---|---|---|---|
| `POST /auth/user/2fa/admin-disable` | sanctum + super-admin | `{user_id, reason}` | `{success: true}` (audit-logged) |

## 6. Work items (~3–4 d)

### Backend (~2.5 d)

- [ ] `composer require pragmarx/google2fa bacon/bacon-qr-code` (verify
      Laravel 13 + PHP 8.3 compatibility before committing).
- [ ] Migration: four columns on `users` (additive) + the
      `auth_two_factor_challenges` table.
- [ ] `UserSchema` constants + `User` model: fillable, casts, hidden
      (secret + codes + last_used_step never serialize).
- [ ] `TwoFactorChallengeService`: `issue()`, `consume()` (atomic, type-checked,
      expiry-checked, attempt-counted), `recordFailure()`, `pruneExpired()`
      (scheduled, hourly — expired rows are garbage, not security state).
- [ ] `TwoFactorService`: `generateSecret()`, `qrCodeUri($secret, $email)`,
      `verifyCode($secret, $code, &$matchedStep)` (returns the matched time-step
      for replay tracking), `verifyAndConsumeRecoveryCode($user, $code)` (atomic,
      D5), `generateRecoveryCodes()`, `clearAll()` (D14 lifecycle).
- [ ] `SignInAction` change: after activation check, if `two_factor_confirmed_at`
      is non-null → issue a `sign_in` challenge with the sign-in context and
      return `{two_factor_required: true, challenge_token}`; if super-admin +
      force config + null confirmed_at → issue an `enrollment` challenge and
      return `{two_factor_enrollment_required: true, challenge_token}`.
      Otherwise, current path unchanged. **The device-token delete and
      UserSignedInEvent move to after successful verification**, not the
      password step.
- [ ] `VerifyTwoFactorAction` + controller + route: consume the challenge
      (atomic, type=`sign_in`), layered rate limiting, try TOTP (with replay
      guard) → recovery code (atomic), then execute the FULL sign-in sequence:
      device-token replace, `createToken()` with the challenge's stored TTL
      context, `UserSignedInEvent` with the stored cart token.
- [ ] Enrollment endpoints (public, enrollment-challenge-gated): start (generate
      secret, store encrypted, return QR) and confirm (verify code, set
      confirmed_at, generate recovery codes).
- [ ] User self-service endpoints: status, enable, confirm, disable, regenerate.
- [ ] Privileged recovery endpoint: super-admin check + mandatory reason +
      audit log entry.
- [ ] Rate limiters: `throttle:auth-2fa-verify` (10/min IP), per-challenge
      attempt cap (5, stored on the row), per-account failure counter (10/15 min,
      cache-based — mirrors the existing OTP lockout pattern).
- [ ] `two_factor_challenges:prune` scheduled command (hourly).
- [ ] Error codes + lang ×6: `auth.two_factor.{invalid_code, challenge_expired,
      challenge_invalid, too_many_attempts, not_enrolled, already_enrolled,
      enrollment_required}`.
- [ ] Storefront reconciliation: audit `settings.tsx` for the existing email-2FA
      text; remove or correct it (the backend has no email-2FA feature).
- [ ] Tests: see §7.

### Admin frontend (~1 d)

- [ ] Sign-in form: handle `{two_factor_required}` → OTP input → call verify →
      store token → staff gate → redirect.
- [ ] Sign-in form: handle `{two_factor_enrollment_required}` → redirect to
      the enrollment wizard.
- [ ] Enrollment wizard (standalone, not behind the dashboard): QR + secret →
      code input → confirm → recovery codes display.
- [ ] 2FA management section (profile settings): status, enable, disable
      (with code prompt), regenerate recovery codes.
- [ ] i18n keys ×6.

## 7. Acceptance tests (bypass paths + concurrency + behavior preservation)

**Sign-in + verification:**
- [ ] 2FA enrolled → `{two_factor_required: true}` + challenge, no bearer token.
- [ ] Correct TOTP code → normal `{user, token}`; challenge consumed.
- [ ] Wrong TOTP code → 400; challenge stays valid (within 5 attempts).
- [ ] Recovery code → normal response; code consumed atomically.
- [ ] Challenge >2 min old → 400 `challenge_expired`.
- [ ] Unknown challenge → 400 (same error — don't confirm existence).
- [ ] Non-2FA user → sign-in unchanged.
- [ ] Sign-in response preserves: device-token replace happened, correct TTL
      (`remember=false` → short TTL), `UserSignedInEvent` fired with cart token.

**Forced enrollment (super-admins):**
- [ ] Super-admin without 2FA + force config → enrollment challenge, no token.
- [ ] Enrollment wizard via challenge: start → QR shown, confirm → recovery codes.
- [ ] After enrollment, sign-in proceeds normally.
- [ ] Force config off → super-admin signs in without 2FA.

**Challenge isolation:**
- [ ] Sign-in challenge used on enrollment endpoints → rejected.
- [ ] Enrollment challenge used on verify endpoint → rejected.
- [ ] Enrollment challenge used for normal API access → 401.
- [ ] Expired enrollment challenge → rejected.

**Concurrency (the review's core additions):**
- [ ] Two concurrent verify requests with the same challenge → exactly one succeeds.
- [ ] Two concurrent requests with the same recovery code → exactly one succeeds.
- [ ] Same TOTP code submitted twice sequentially within the 30s window → second rejected.
- [ ] Concurrent TOTP submissions → replay guard holds.

**Rate limiting:**
- [ ] 6th failed attempt on one challenge → challenge invalidated (5-attempt cap).
- [ ] 11th failed attempt on one account → temporary block.
- [ ] Rate limiter is per-account, not just per-IP.

**Enrollment lifecycle (D14):**
- [ ] Enable → returns QR + secret with `Cache-Control: no-store`.
- [ ] Secret stored encrypted in DB (not plaintext).
- [ ] Confirm with correct code → confirmed_at set; recovery codes returned once.
- [ ] Confirm with wrong code → 400; confirmed_at stays null.
- [ ] Unconfirmed secret cannot authenticate (sign-in still returns no 2FA).
- [ ] Disable with correct code → all four columns cleared atomically.
- [ ] Disable with wrong code → 400; 2FA stays active.
- [ ] Disable with just password → 400.
- [ ] Regenerate recovery codes → old codes invalid immediately.
- [ ] Secret and recovery codes never appear in serialized user resources.

**Privileged recovery:**
- [ ] Super-admin disables another user's 2FA → success, audit-logged.
- [ ] Non-super-admin attempts → 403.
- [ ] Missing reason → 422.

## 8. Edge cases

| Scenario | Behavior |
|---|---|
| User loses device + all recovery codes | Super-admin force-disable via `admin-disable` (audit-logged) → user re-enrolls |
| Clock drift | ±1 code window (30 s standard); extreme drift requires re-enrollment |
| Concurrent sign-in attempts | Each gets its own challenge; both can complete independently (explicit decision — see D4 and the roadmap update in §10) |
| 2FA enabled mid-session | Existing token stays valid until expiry; the next sign-in requires 2FA |
| Super-admin force-enrolls while already signed in | Current session unaffected; next sign-in gates |

## 9. Explicit non-goals

Customer-facing 2FA UI (S.1b). Store-owner-configurable enforcement for regular
admins (S.1b). SMS-based 2FA. WebAuthn/hardware keys. Email one-time codes as
a second factor. A separate 2FA module (this lives in Auth).

## 10. Roadmap updates

`SECURITY_ROADMAP.md` S.1 gets two corrections: D8 ("all users") becomes
"admin users in S.1; customers in S.1b"; the edge-case table's "only the
latest challenge is valid" becomes "each challenge is independent" to match
D4's explicit decision.

## 11. Suggested commits

1. `feat(backend/auth): totp two-factor backend with atomic challenges`
2. `feat(admin): two-factor sign-in flow and enrollment wizard`
3. `docs(monorepo): record s1 two-factor execution`

## 12. Review trail

- **Rev 1 (2026-11-10):** drafted from the security roadmap's S.1 outline.
- **Adversarial review (2026-11-10, 7.8/10):** three critical findings —
  the forced-enrollment authentication deadlock (D6 contradicted D7),
  non-atomic recovery-code consumption, non-atomic challenge consumption —
  plus six important findings (TOTP replay, layered rate limiting, route
  placement vs PermissionMiddleware, sign-in context preservation, scope
  inconsistency, missing concurrency tests). All folded into rev 2:
  the enrollment challenge (D9), DB-backed challenges with atomic
  consumption (D4/D6), row-locked recovery codes (D5), TOTP replay
  tracking (D8), layered rate limits (D10), user-route placement (D7),
  sign-in context carried on the challenge (D3), privileged recovery
  (D12), scope narrowed to admins (D13), secret lifecycle rules (D14),
  and the full concurrency/isolation test matrix (§7). Budget raised
  from 2 d to 3–4 d per the reviewer's calibration.
