# Plan S.1 — Two-factor authentication on admin accounts (TOTP)

> **Purpose of this document:** Self-contained execution plan for Phase S.1
> of `docs/SECURITY_ROADMAP.md`. TOTP is a security control — every bypass
> path gets explicit design, and the tests target the bypass attempts, not
> just the happy path. **The standing invariant: a challenge, TOTP time-step,
> or recovery code must never be accepted twice, regardless of how many
> concurrent requests arrive. No bearer token may be issued after 2FA has
> been disabled, regardless of when the verification started.**
>
> **Created:** 2026-11-10 · **Revised:** 2026-11-12 — rev 2 (review 1,
> 7.8/10), rev 3 (review 2, 8.8/10), rev 4 (reviews 3+4, 9.1/10, approved);
> see §12 · **Owner:** solo maintainer · **Status:** rev 4, approved for
> implementation
>
> **How to use:** Re-verify ⚠-marked facts before relying on them. Slice
> strictly in order: **backend → frontend → policy**. Conventional commits;
> never commit unless asked; heavy commands `nice -n 10`; Larastan before
> Pest. **Budget: 3–4 d.**

---

## 1. Where this fits

The admin panel controls refunds, voids invoices, changes settings, and
manages payment methods. Today it's guarded by a single factor. This slice
adds TOTP as a second factor for **admin users**:

| Role | Behavior |
|---|---|
| Regular admin | **Opt-in** — enable/disable from profile settings; sign-in unchanged without it |
| Super-admin | **Forced** — enrollment wizard on next sign-in (config `auth.two_factor.force_super_admin`, default on, env-overridable) |
| All-admins enforcement | Deferred to S.1b — store-owner flips a setting |

Customer-facing 2FA and store-owner enforcement are S.1b, not this slice.

## 2. Verified state (2026-11-12)

| Fact | Evidence |
|---|---|
| Sign-in flow | `SignInAction::handle()` — guard attempt → verify → activate → device-token replace → `createToken(deviceName, ['*'], ttl)` → fires `UserSignedInEvent` (with cart token) → returns `{user, token}` |
| Token model | One token per device; opaque Sanctum tokens with per-device TTL |
| User model | `$fillable` = [email, email_verified_at, password, is_active, comment]; `$hidden` = [password, remember_token] |
| Users migration | `Modules/Auth/database/migrations/0001_01_01_000000_create_users_table.php` — pre-v1, edit in place per AGENTS.md convention |
| Admin sign-in surface | `apps/admin/src/features/auth/sign-in/` — form → POST → zustand → staff gate |
| Admin routing | `admin.php` wraps in `auth:sanctum` + `PermissionMiddleware` — 2FA self-service must NOT land here |
| User routing | `user.php` wraps in `auth:sanctum` only — the right home |
| Auth module DB prefix | `Module::DB_PREFIX` = `auth_` |
| Rate limiting | `throttle:auth-sign-in` (IP-based) |
| No TOTP package | `composer.json` has no google2fa |
| Storefront 2FA text | ⚠ `settings.tsx` has email-2FA text — needs reconciliation |
| SQLite tests | Suite runs on `:memory:` SQLite — sufficient for features but **cannot validate row-lock concurrency**; dev compose runs MySQL |
| Cache backend | Redis in dev + prod compose — shared cache for rate limiting |
| Pre-v1 migration rule | AGENTS.md: "edit the original table migration in place instead of adding a drop_* migration" |

## 3. Design decisions

### Core authentication flow

| # | Decision | Why |
|---|---|---|
| D1 | **`pragmarx/google2fa`** + `bacon/bacon-qr-code`. Verify compatibility with Laravel 13 + PHP 8.3; record versions. **Use `verifyKeyNewer()`** which returns the matched timestamp — NOT a boolean-only `verifyKey()`. Unit test proves the returned step is the one matched, including drift-window acceptance. | We need the matched time-step for replay prevention (D8). |
| D2 | **Four columns added to the EXISTING `create_users_table` migration** (pre-v1, edit in place per AGENTS.md): `two_factor_secret` (encrypted), `two_factor_recovery_codes` (text — JSON of bcrypt hashes), `two_factor_confirmed_at` (timestamp), `two_factor_last_used_step` (bigint). All in `$hidden`. | No separate migration for user columns; the challenges table IS a new migration (new table). |
| D3 | **Sign-in is two-step via a DB-backed challenge.** 2FA confirmed → challenge row → `{two_factor_required: true, challenge_token}`. Encrypted context: `{device_name, remember, cart_token}` — explicit allowlist. **TTL: 5 min sign_in, 10 min enrollment.** | Challenge carries sign-in context; different TTLs for different UX needs. |
| D4 | **Opaque token, SHA-256 hash PK.** 32 random bytes → 64-char hex (shown to client once). PK is `token_hash CHAR(64)`. Raw token never stored. Input validated as 64 lowercase hex chars. **Transport: `challenge_token` in the request body** on all three public endpoints. Frontend keeps it in React state only. | Uniformly opaque; no UUID/hash ambiguity. |
| D15 | **Shared `CompleteAuthenticationAction`.** Splits into `issueToken()` (DB-only, safe inside a transaction — device-token replace, `createToken()`, user `is_active` re-check) and `dispatchEvents()` (external side effects — `UserSignedInEvent`, cart-merge event — called after commit). Both the 2FA and non-2FA paths call both halves. | One authoritative completion; the transactional boundary is explicit. |
| D16 | **Encrypted challenge context.** `encrypted` cast on context column. Context nulled on consumption; pruner nulls on expiry. Device_name truncated + sanitized. | Cart_token is a bearer credential. |

### Lock ordering, concurrency, and the token-issuance boundary

| # | Decision | Why |
|---|---|---|
| D17 | **Global lock order: user row FIRST, then challenge rows — always, no exceptions.** For challenge-token operations: (1) read `user_id` WITHOUT locking; (2) lock user row; (3) lock challenge row; (4) revalidate; (5) operate. For user-ID operations: (1) lock user; (2) operate on challenges. | Prevents the ABBA deadlock between verification and disable flows. |
| D6 | **Verification: lock → verify → consume → ISSUE TOKEN → commit → dispatch events.** (1) hash token, read `user_id` (no lock); (2) lock user row; (3) lock challenge row — revalidate (exists, unconsumed, unexpired, correct type, `failed_attempts < 5`, user `is_active`); (4) verify code under the user lock; (5) if **invalid**: increment `failed_attempts`, commit, error; if **valid**: consume challenge, **issue the Sanctum token inside this transaction** (user lock still held — a concurrent disable cannot interleave), commit; (6) after commit: `dispatchEvents()`. | The reviewer's token-issuance race fix: the token is issued while the user lock prevents a concurrent disable from clearing 2FA between verification commit and token creation. |
| D8 | **TOTP replay prevention — every TOTP acceptance updates the guard.** Sign-in verification, self-service enrollment confirmation, forced-enrollment confirmation ALL record the matched time-step in `two_factor_last_used_step` under the user-row lock. Any subsequent TOTP for the same user rejects matched steps ≤ the stored value. | Enrollment confirmation is a TOTP acceptance; without the guard, the same code can confirm enrollment then replay for sign-in. |
| D5 | **Eight recovery codes, 10 chars from a 32-char alphanumeric alphabet** (no 0/O/1/I), ~50 bits per code. Format `XXXXX-XXXXX`; input accepts both forms. **6-digit is TOTP-only, never recovery codes.** Consumption: inside the D17 transaction, verify against every hash (no position disclosure), remove only the matched hash. | High entropy; no format ambiguity. |

### Enrollment, challenges, rate limiting, scope

| # | Decision | Why |
|---|---|---|
| D9 | **Two challenge types, strictly isolated.** `sign_in` → verify only. `enrollment` → start/confirm only. Enrollment state machine: issued → active → started → confirmed (consumed atomically, replay guard updated). Failure: expired / abandoned / re-issued. **Limits:** max 3 sign_in, max 1 enrollment per user — enforced inside the D17 transaction. **Invalidation triggers:** user deactivated, password reset, 2FA disabled, enrollment replaced. | Explicit states. |
| D7 | **Self-service 2FA under user routes** (`auth:sanctum`, no PermissionMiddleware). | 2FA is self-service, not a business permission. |
| D10 | **Rate limiting via Laravel's `RateLimiter` facade** (`hit()` + `tooManyAttempts()` — atomic fixed-window on the configured driver). Three layers: per-IP 10/min, per-challenge 5 attempts (on the row, atomic in D6), per-account 10/15-min (`RateLimiter` key `2fa:{user_id}`). **TOTP/recovery failures increment; password failures don't** (existing throttle). **Full auth (both factors + token) resets; password-only doesn't.** **Account-lock DoS tradeoff acknowledged:** an attacker who knows an admin's email can trigger the lock — this is a denial against one account, not a bypass; per-IP and per-challenge layers make it non-trivially expensive. | `RateLimiter` provides correct atomic semantics; the tradeoff is deliberate. |
| D11 | **Disenrollment requires a valid TOTP or recovery code** (D5 atomic, D17 order). | Password alone can't kill 2FA. |
| D12 | **Privileged recovery:** super-admin `admin-disable` + mandatory reason + audit log. Clears all four columns + invalidates challenges. D17 order. | The "lost everything" path. |
| D13 | **Admin users only in S.1.** Opt-in for regular admins; forced for super-admins (config-gated). | Honest scope. |
| D14 | **Secret lifecycle:** new secret replaces unconfirmed; unconfirmed can't authenticate; codes after confirmation only; disable clears all + challenges; regeneration atomic; QR + secret no-store; secret never returned after confirmation; all in `$hidden`; recovery codes shown once with "save now" UI. | Explicit lifecycle. |

## 4. Schema

**Users table — columns added to the EXISTING migration** (pre-v1 edit in place):
`Modules/Auth/database/migrations/0001_01_01_000000_create_users_table.php`

```php
// Inside the existing Schema::create(UserSchema::TABLE, ...) block:
$table->text(UserSchema::TWO_FACTOR_SECRET)->nullable();
$table->text(UserSchema::TWO_FACTOR_RECOVERY_CODES)->nullable();
$table->timestamp(UserSchema::TWO_FACTOR_CONFIRMED_AT)->nullable();
$table->bigInteger(UserSchema::TWO_FACTOR_LAST_USED_STEP)->nullable();
```

**Challenges table — new migration** (new table, gets its own file):
`Modules/Auth/database/migrations/2026_11_12_000001_create_two_factor_challenges_table.php`

```php
Schema::create(TwoFactorChallengeSchema::TABLE, function (Blueprint $table) {
    $table->char(TwoFactorChallengeSchema::TOKEN_HASH, 64)->primary();
    $table->foreignId(TwoFactorChallengeSchema::USER_ID)
        ->constrained(UserSchema::TABLE)->cascadeOnDelete();
    $table->string(TwoFactorChallengeSchema::TYPE);
    $table->text(TwoFactorChallengeSchema::CONTEXT)->nullable();
    $table->unsignedInteger(TwoFactorChallengeSchema::FAILED_ATTEMPTS)->default(0);
    $table->timestamp(TwoFactorChallengeSchema::EXPIRES_AT);
    $table->timestamp(TwoFactorChallengeSchema::CONSUMED_AT)->nullable();
    $table->timestamps();
    $table->index([TwoFactorChallengeSchema::USER_ID, TwoFactorChallengeSchema::TYPE]);
    $table->index(TwoFactorChallengeSchema::EXPIRES_AT);
});
```

## 5. API surface

### Public (challenge-token gated, rate-limited)

`challenge_token` in the request body on all three endpoints.

| Route | Challenge type | Body | Response |
|---|---|---|---|
| `POST /auth/public/auth/2fa/verify` | sign_in | `{challenge_token, code}` | `{user, token, settings}` |
| `POST /auth/public/auth/2fa/enrollment/start` | enrollment | `{challenge_token}` | `{secret, qr_code_uri}` |
| `POST /auth/public/auth/2fa/enrollment/confirm` | enrollment | `{challenge_token, code}` | `{recovery_codes: [...]}` |

### User routes (auth:sanctum, no PermissionMiddleware)

| Route | Body | Response |
|---|---|---|
| `GET /auth/user/2fa/status` | — | `{enrolled, confirmed_at, recovery_codes_remaining}` |
| `POST /auth/user/2fa/enable` | — | `{secret, qr_code_uri}` (no-store) |
| `POST /auth/user/2fa/confirm` | `{code}` | `{recovery_codes: [...]}` |
| `POST /auth/user/2fa/disable` | `{code}` | `{success: true}` |
| `POST /auth/user/2fa/recovery-codes` | `{code}` | `{recovery_codes: [...]}` |
| `POST /auth/user/2fa/admin-disable` | `{user_id, reason}` | super-admin only |

## 6. Work items (~3–4 d)

### Backend (~2.5 d)

- [ ] Verify `pragmarx/google2fa` + `bacon/bacon-qr-code` compatibility; record versions; test `verifyKeyNewer()` returns the matched step.
- [ ] Edit the existing `create_users_table` migration (add four columns per §4).
- [ ] New migration for the challenges table (per §4).
- [ ] Schema constants + models (encrypted casts, `$hidden`).
- [ ] `TwoFactorChallengeService`: `issue()` (D17), `resolveForUser()`, `recordFailure()`, `invalidateAllForUser()`, `pruneExpired()` (nulls context on expiry).
- [ ] `TwoFactorService`: `generateSecret()`, `qrCodeUri()`, `verifyAndRecordStep()`, `verifyAndConsumeRecoveryCode()`, `generateRecoveryCodes()`, `clearAll()`.
- [ ] `CompleteAuthenticationAction`: `issueToken()` (DB-only, in-transaction) + `dispatchEvents()` (after commit).
- [ ] `SignInAction`: 2FA branch → sign_in challenge; super-admin force branch → enrollment challenge.
- [ ] `VerifyTwoFactorAction`: D6 sequence (lock user → lock challenge → verify → consume → issue token in-transaction → commit → dispatch).
- [ ] Enrollment endpoints (public, challenge-gated).
- [ ] User self-service endpoints.
- [ ] Privileged recovery (D12).
- [ ] Rate limiters via `RateLimiter` (D10).
- [ ] Prune command (hourly).
- [ ] Error codes + lang ×6.
- [ ] Storefront reconciliation.
- [ ] Tests: §7.

### MySQL concurrency integration tests (~0.5 d)

- [ ] Concurrent same-challenge → one succeeds.
- [ ] Concurrent same-recovery-code → one succeeds.
- [ ] Same TOTP across two challenges → second rejected.
- [ ] Concurrent enrollment double-confirm → one succeeds.
- [ ] Regeneration racing with consumption → clean.
- [ ] **Verification racing with disable → no deadlock AND no token after disable.**
- [ ] Concurrent challenge issuance respects limits.

### Admin frontend (~1 d)

- [ ] Sign-in form: both challenge types → OTP/enrollment flow.
- [ ] Enrollment wizard: QR → code → confirm → recovery codes ("save now").
- [ ] 2FA management section (profile settings).
- [ ] i18n ×6.

## 7. Acceptance tests

*(Functional on SQLite; concurrency on MySQL.)*

**Sign-in:** 2FA enrolled → challenge. / Correct TOTP → token. / Wrong → retry. / 6th failure → invalidated. / Recovery code → consumed. / Expired → 400. / Non-2FA → unchanged. / Preserves device-token, TTL, event, cart-merge. / Deactivated user → refused.

**Forced enrollment:** Super-admin → wizard. / Completed. / Expired → re-auth. / Abandoned secret unusable. / **Enrollment TOTP replayed for sign-in → rejected.**

**Challenge isolation:** Cross-type → rejected. / API access → 401. / New enrollment invalidates old.

**Concurrency (MySQL):** All seven from §6, including **verification vs disable → no token after disable**.

**Rate limiting:** Per-challenge cap / per-account block / full auth resets / password-only doesn't.

**Lifecycle:** QR no-store / secret encrypted / codes once / disable clears all / wrong code 400 / password-only 400 / regeneration immediate / hidden fields never serialize.

**Privileged recovery:** Super-admin works / non-super 403 / missing reason 422.

**Data:** User deleted → challenges cascade / limits enforced / pruner nulls context.

## 8. Edge cases

Loses device + codes → super-admin disable. / Clock drift → ±1 window. / Concurrent sign-ins → independent (max 3). / 2FA mid-session → existing token until expiry. / Recovery screen closed → unrecoverable, UI warns. / Deactivated during challenge → invalidated. / Password reset during challenge → invalidated. / bcrypt latency → benchmark, parallel if >500 ms. / Multi-instance file cache → needs Redis, deployment note. / Attacker triggers account lock → acknowledged DoS tradeoff.

## 9. Non-goals

Customer 2FA UI (S.1b). Store-owner enforcement (S.1b). SMS. WebAuthn. Email OTP. Separate module.

## 10. Roadmap updates

S.1 scope → "admin users, opt-in for regular admins, forced for super-admins"; concurrent challenges → "independent (max 3)."

## 11. Suggested commits

1. `feat(backend/auth): totp two-factor backend with atomic challenges`
2. `test(backend/auth): mysql concurrency integration tests`
3. `feat(admin): two-factor sign-in flow and enrollment wizard`
4. `docs(monorepo): record s1 two-factor execution`

## 12. Review trail

- **Rev 1 (2026-11-10):** drafted.
- **Review 1 (7.8/10):** enrollment deadlock, non-atomic recovery/challenges, TOTP replay, rate limiting, route placement, sign-in context, scope. → Rev 2.
- **Review 2 (8.8/10):** UUID/hash contradiction, challenge-before-verify, enrollment state machine, user-row lock, encrypt context, atomic limiter, migration conventions. → Rev 3.
- **Review 3 (9.1/10):** lock-order deadlock → D17; enrollment replay → D8 every acceptance; SQLite can't validate → MySQL tests. Clarifications: TTLs, transport, entropy, cascade FK. Architecture approved. → Rev 4 draft.
- **Review 4 (final):** token issued after transaction commit → **D6 now issues the Sanctum token INSIDE the transaction under the user lock**; `CompleteAuthenticationAction` splits into `issueToken()` + `dispatchEvents()`. `Cache::add()`+`increment()` not atomic → **D10 uses `RateLimiter` facade**. Account-lock DoS tradeoff acknowledged. Migration correction: user columns edited into the original `create_users_table` migration per the pre-v1 AGENTS.md convention, not a separate migration. **Approved for implementation.**
