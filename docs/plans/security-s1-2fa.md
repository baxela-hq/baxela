# Plan S.1 — Two-factor authentication on admin accounts (TOTP)

> **Purpose of this document:** Self-contained execution plan for Phase S.1
> of `docs/SECURITY_ROADMAP.md`. Like the gap and extension plans, this
> document stands alone: verified state, design decisions, work items,
> edge cases, and the acceptance-test list. TOTP is a security control —
> every bypass path gets explicit design, and the tests target the bypass
> attempts, not just the happy path.
>
> **Created:** 2026-11-10 · **Owner:** solo maintainer · **Status:** drafted,
> awaiting the adversarial review pass (house convention for security slices)
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Slice
> strictly in order: **backend → frontend → policy**. Conventional commits;
> never commit unless asked; heavy commands `nice -n 10`; Larastan before
> Pest.

---

## 1. Where this fits

The admin panel controls refunds, voids invoices, changes settings, and
manages payment methods. Today it's guarded by a single factor (password +
rate limiting). This slice adds TOTP (time-based one-time passwords,
RFC 6238) as a second factor for admin accounts — opt-in per user, forced
for super-admins, with recovery codes for lockout recovery.

## 2. Verified state (2026-11-10)

| Fact | Evidence |
|---|---|
| Sign-in flow | `SignInAction::handle()` — `Auth::guard('user_session')->attempt()` → verify → activate → device-token replace → `createToken(deviceName, ['*'], ttl)` → returns `{user, token}` |
| Token model | One token per device (old token for same device deleted on re-login); opaque Sanctum tokens with per-device TTL from config |
| User model | `User::$fillable` = [email, email_verified_at, password, is_active, comment]; `$hidden` = [password, remember_token]; no 2FA columns |
| Admin sign-in surface | `apps/admin/src/features/auth/sign-in/` — React form → `POST /auth/public/auth/signin` → stores token in zustand → `fetchAdminAccount()` (staff gate) → redirect |
| Admin profile surface | No profile/settings page for auth factors today; settings are under `/settings/store` (store-level, not user-level) |
| Rate limiting | `throttle:auth-sign-in` on the sign-in route |
| No TOTP package | `composer.json` has no google2fa, otphp, or similar |
| GuardsEnum | `USER_SESSION` used for the session guard; no separate admin guard |

## 3. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 | **`pragmarx/google2fa-laravel`** (wraps `pragmarx/google2fa` + `bacon/bacon-qr-code`). Pure PHP, no external service, shared-hosting safe. Generates the TOTP secret and the QR-code data URI. | The alternative (`spatie/laravel-google2fa`) is a thin wrapper around the same underlying library; pragmarx's Laravel integration handles the session/flow plumbing. |
| D2 | **Three-column storage on `users`:** `two_factor_secret` (encrypted, nullable), `two_factor_recovery_codes` (hashed JSON array, nullable), `two_factor_confirmed_at` (timestamp, nullable — null = not enrolled). The secret is encrypted at rest (Laravel's `encrypt()` cast) because it's the TOTP shared secret — a leak means an attacker can generate codes. | Three nullable columns keep the model honest: null = not enrolled; confirmed_at = enrolled and active. No separate table needed. |
| D3 | **Sign-in flow is two-step, not modal.** Password valid + 2FA enrolled → respond `{two_factor_required: true, challenge_token: "<uuid>"}` with HTTP 200 and **no bearer token**. The client shows an OTP input. `POST /auth/public/auth/2fa/verify` with `{challenge_token, code, device_name}` → normal `{user, token}` response. | Keeps the existing password path unchanged for non-2FA users; the challenge token is the bridge. The token is single-use, 2-minute TTL, stored in cache. |
| D4 | **Challenge token:** a UUID stored in cache (`2fa:challenge:{uuid}` → user_id), TTL 2 minutes, deleted on first use (read-then-delete). Not a JWT, not a DB row — a cache entry that evaporates. | Simple, no schema change, self-cleaning. If the user takes >2 min to type the code, they start over — the right tradeoff for a security control. |
| D5 | **Eight one-time recovery codes**, generated at enrollment, shown once, stored as a hashed JSON array (`password_hash()` per code). Each is single-use: consumed by removing it from the array. "Regenerate codes" invalidates all old codes. | bcrypt-hashing each code means a DB leak doesn't reveal usable codes. Eight codes is the GitHub/Google standard. |
| D6 | **Enrollment is admin-profile-only** (`/auth/admin/2fa/*` routes behind `auth:sanctum`), not on the public sign-in surface. A user must be authenticated to enable/disable 2FA. Disenrollment requires a valid TOTP code OR a valid recovery code (not just the password). | Prevents an attacker who phishes a password from silently disabling the second factor. |
| D7 | **Forced enrollment for super-admins:** a config gate (`auth.two_factor.force_super_admin`, default true). On sign-in, a super-admin without confirmed 2FA gets `{two_factor_enrollment_required: true, challenge_token}` instead of a bearer token — the client routes to the enrollment wizard, not the dashboard. | The super-admin can disable 2FA for other admins but shouldn't be able to skip it for themselves. The escape hatch: the config (env var) can set this to false for edge-case deployments. |
| D8 | **All users can opt in**, not just admins — the storefront customer who wants 2FA on their account gets the same flow. The admin UI is the first consumer; a storefront profile section is a follow-up (not in this slice). | The backend is user-scoped, not role-scoped. One flow, one set of endpoints. |
| D9 | **Clock tolerance:** the standard ±1 code window (30 s). No custom tolerance. | The TOTP standard's default is correct for real-world clock drift; widening it weakens the factor. |
| D10 | **The OTP input accepts TOTP codes and recovery codes interchangeably** on the verify endpoint. The backend tries TOTP first, then recovery-code match. | One input field, one API call — the client doesn't need to know which type the user is typing. Recovery codes are distinguished by format (8×`xxxx-xxxx` vs 6-digit). |

## 4. Schema sketch

```sql
ALTER TABLE users
  ADD COLUMN two_factor_secret TEXT NULL,              -- encrypted(TOTP secret)
  ADD COLUMN two_factor_recovery_codes TEXT NULL,      -- JSON array of bcrypt hashes
  ADD COLUMN two_factor_confirmed_at TIMESTAMP NULL;   -- null = not enrolled
```

## 5. API surface

### Public (no auth required — part of the sign-in flow)

| Route | Body | Response | Notes |
|---|---|---|---|
| `POST /auth/public/auth/2fa/verify` | `{challenge_token, code, device_name, remember}` | `{user, token, settings}` or `{two_factor_enrollment_required: true}` | Tries TOTP → recovery. Single-use challenge token. Rate-limited. |

### Admin (auth:sanctum required)

| Route | Body | Response | Notes |
|---|---|---|---|
| `GET /auth/admin/2fa/status` | — | `{enrolled, confirmed_at, recovery_codes_remaining}` | Read-only status for the profile UI |
| `POST /auth/admin/2fa/enable` | — | `{secret, qr_code_uri}` | Generates + stores the secret (unconfirmed). Calling twice replaces the unconfirmed secret. |
| `POST /auth/admin/2fa/confirm` | `{code}` | `{recovery_codes: [...]}` | Validates the code against the stored secret; sets confirmed_at; generates recovery codes (shown once). |
| `POST /auth/admin/2fa/disable` | `{code}` | `{success: true}` | Requires a valid TOTP OR recovery code. Clears all three columns. Audit-logged. |
| `POST /auth/admin/2fa/recovery-codes` | `{code}` | `{recovery_codes: [...]}` | Requires a valid code. Regenerates (invalidates old codes). Shown once. |

## 6. Work items (~2 d)

### Backend (~1.5 d)

- [ ] `composer require pragmarx/google2fa-laravel` (the Laravel integration).
- [ ] Migration: three nullable columns on `users` (additive).
- [ ] `UserSchema` constants + `User` model: fillable, casts
      (`two_factor_secret` => encrypted, `two_factor_recovery_codes` => array,
      `two_factor_confirmed_at` => datetime), hidden (both secret and codes).
- [ ] `TwoFactorService` (Auth module Support):
      `generateSecret()`, `qrCodeUri($secret, $email)`, `verifyCode($secret, $code)`,
      `consumeRecoveryCode($user, $code)`, `generateRecoveryCodes()`,
      `challengeToken($userId)`, `resolveChallenge($token)`.
- [ ] `SignInAction` change: after activation check, if `two_factor_confirmed_at`
      is non-null → return the challenge response (no bearer token); if
      super-admin + force config + null confirmed_at → return the enrollment
      challenge. Otherwise, current path unchanged.
- [ ] `VerifyTwoFactorAction` + `VerifyTwoFactorController` + route: resolves
      the challenge token, tries TOTP → recovery code, issues the normal
      token response on success.
- [ ] Admin 2FA endpoints: status, enable, confirm, disable, regenerate
      recovery codes — five actions + controllers + routes under
      `auth/admin/2fa/*`.
- [ ] Error codes + lang ×6: `auth.two_factor.invalid_code`,
      `auth.two_factor.challenge_expired`, `auth.two_factor.not_enrolled`,
      `auth.two_factor.already_enrolled`.
- [ ] Tests: see §7.

### Admin frontend (~0.5 d)

- [ ] Sign-in form: handle `{two_factor_required: true}` → show OTP input →
      call `POST /2fa/verify` → store token → redirect.
- [ ] Sign-in form: handle `{two_factor_enrollment_required: true}` →
      redirect to the enrollment wizard.
- [ ] 2FA enrollment wizard (profile settings section):
      "Enable 2FA" → show QR + secret → user enters code → confirm →
      show recovery codes once → done.
- [ ] 2FA management: status display, disable (with code prompt),
      regenerate recovery codes.
- [ ] i18n keys ×6 for the 2FA UI.

## 7. Acceptance tests (the bypass paths, not just the happy path)

**Sign-in + verification:**
- [ ] Sign-in with 2FA enrolled → returns `{two_factor_required: true}` + challenge token, **no bearer token**.
- [ ] Correct TOTP code → normal `{user, token}` response; challenge token consumed (second use fails).
- [ ] Wrong TOTP code → 400 `auth.two_factor.invalid_code`; challenge token stays valid (retry within TTL).
- [ ] Correct recovery code → normal response; recovery code consumed (second use fails).
- [ ] Challenge token older than 2 min → 400 `auth.two_factor.challenge_expired`.
- [ ] Unknown challenge token → 400 (same error — don't confirm the token existed).
- [ ] User without 2FA → sign-in response unchanged (no two_factor_required field).

**Forced enrollment (super-admins):**
- [ ] Super-admin without 2FA + force config on → `{two_factor_enrollment_required: true}` + challenge token, no bearer token.
- [ ] Super-admin completes enrollment → sign-in proceeds.
- [ ] Force config off → super-admin without 2FA signs in normally.

**Enrollment + management:**
- [ ] Enable → returns QR + secret; secret stored encrypted (not plaintext in DB).
- [ ] Confirm with correct code → confirmed_at set; recovery codes returned once.
- [ ] Confirm with wrong code → 400; confirmed_at stays null.
- [ ] Disable with correct code → columns cleared; sign-in no longer requires 2FA.
- [ ] Disable with wrong code → 400; 2FA stays active.
- [ ] Disable with just the password (no code) → 400.
- [ ] Regenerate recovery codes with valid code → new codes; old codes invalid.

**Bypass attempts:**
- [ ] Calling `POST /2fa/verify` without going through sign-in (no challenge token) → 400.
- [ ] Calling admin 2FA endpoints unauthenticated → 401.
- [ ] Using an expired challenge token → 400, not a stale token acceptance.
- [ ] Recovery code replay after regeneration → fails.
- [ ] Sign-in with `two_factor_required` response, then trying to access an admin endpoint with no token → 401.

## 8. Edge cases

| Scenario | Behavior |
|---|---|
| User loses device + all recovery codes | Another admin or super-admin can force-disable via a super-admin endpoint (audit-logged) |
| Clock drift between server and user device | ±1 code window (30 s standard) absorbs normal drift; extreme drift requires re-enrollment |
| Concurrent sign-in attempts | Each gets its own challenge token; both can complete independently |
| User enables 2FA then clears cache mid-session | The secret is in the DB, not cache — no impact; the challenge token IS in cache (short-lived, by design) |
| 2FA on a customer account (non-admin) | The backend is user-scoped; a storefront UI for customer 2FA is a follow-up, not in this slice |
| Multiple failed verification attempts | Rate-limited by the existing `throttle:auth-sign-in` (the verify route gets its own limiter) |

## 9. Explicit non-goals

SMS-based 2FA (carrier dependency, cost, SIM-swap risk). WebAuthn/hardware
keys (a future phase — the schema and service can accommodate it). 2FA on
the storefront UI for customers (backend is ready; the UI is a follow-up).
Email-based one-time codes as a second factor (weaker than TOTP; the OTP
infrastructure is for account verification, not a second factor).

## 10. Suggested commits

1. `feat(backend/auth): totp two-factor backend with challenge-token sign-in`
2. `feat(admin): two-factor sign-in flow and enrollment wizard`

## 11. Related docs

- `docs/SECURITY_ROADMAP.md` — phase S.1 (this plan) + S.2–S.4
- `apps/backend/AGENTS.md` — module conventions
- `docs/plans/launch-readiness.md` — the existing auth-surface verification
