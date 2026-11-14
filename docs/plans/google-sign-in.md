# Plan G.1 — Sign in with Google (storefront customers)

> **Purpose of this document:** Self-contained execution plan for Google
> sign-in as a customer authentication method, designed around the existing
> headless architecture (Sanctum bearer tokens in localStorage, one
> `auth_users` table, `CompleteAuthenticationAction` as the only token
> issuance path). **The standing invariant: email matching alone never
> authorizes linking or activation; every account mutation is atomic and
> preceded by eligibility checks; token issuance always runs through
> `CompleteAuthenticationAction::issueToken()`.**
>
> **Created:** 2026-11-14 · **Revised:** rev 2 (review 1, 8.4/10), rev 3
> (review 2, 9.2/10), rev 4 (review 3, 9.6/10), rev 5 (review 4, 9.1/10),
> rev 6 (review 5, 9.4/10), rev 7 (review 6, 9.8/10), rev 8 (review 7,
> 9.6/10), rev 9 (review 8, 9.7/10), rev 10 (review 9, 9.8/10), rev 11
> (review 10, 9.3/10), rev 12 (reviews 11–12, 9.9/10 → **10/10 — approved,
> frozen**); see the review-driven change log at the bottom · **Owner:**
> solo maintainer · **Status:** **implemented** (backend + storefront +
> tests live; see "Definition of done")
>
> **How to use:** Slice strictly in order: **backend → storefront →
> policy/docs**. Conventional commits; never commit unless asked; heavy
> commands `nice -n 10`.

---

## 1. Where this fits

Storefront customers have email/password auth only (signup → OTP activation →
sign-in). This adds Google sign-in **for customers**: the official Google
button (GIS JS SDK) POSTs an ID token to a new public endpoint, the backend
verifies it against Google's JWKS (`firebase/php-jwt`), and returns the same
`{token, user}` envelope as sign-in. Admin accounts keep password + forced
TOTP and can never authenticate through Google. `docs/ROADMAP.md` deferred
social login for v0.1.0-alpha; this slice implements the Google part (GitHub
etc. later, per `docs/THIRD_PARTY_SERVICES.md`).

**Scope guard rails (v1):** no automatic account linking, no Google access/
refresh token storage, no Google profile persistence, no second
authentication pipeline.

## 2. Security invariants (each one tested)

1. Google `sub` is the permanent provider identity; email never identifies a
   linked account.
2. No linking in v1 — an email conflict returns `auth.google.email_exists`
   (409); authenticated linking ("Connect Google" in profile settings) is
   deferred (see §7).
3. **Claim validation vs. account policy are separate:** the verifier
   requires `email_verified` to be a genuine boolean (missing/null/malformed
   → reject everywhere). Account policy: Gmail/Googlemail instant activation
   requires `email_verified === true`; Workspace requires `email_verified ===
   true` + valid non-empty `hd` + exact normalized email-domain match;
   present-but-empty/malformed or domain-inconsistent `hd` → reject (never
   silently downgraded to third-party). Third-party emails: either boolean
   accepted → the existing OTP activation flow (the OTP, not the Google
   claim, proves mailbox ownership). Unverified Gmail/Workspace → rejected.
4. Customer-only: administrative predicate = `roles()->exists() ||
   permissions()->exists()` (guard-agnostic; ordinary customers hold none —
   roles are assigned only at seed time) → rejected **before any persistent
   account or authentication-state mutation** (user/social/OTP/device/token
   writes). The verified nonce is consumed before account resolution and is
   intentionally not restored on rejection.
5. Rejection never leaves an association behind; the nonce is burned before
   account work and never restored — even after failed authentication.
6. Per-branch transaction contract (both atomic): Gmail/Workspace →
   transaction { create active+verified user + social row + `issueToken()` }
   → post-commit best-effort sign-in events → `SignInResource`. Third-party
   → transaction { create inactive+unverified user + social row + persist
   OTP, **no token, no device** } → best-effort activation events →
   `requires_activation`. `issueToken()` runs **only** in the active-account
   branch.
7. All tokens through `issueToken()`; its transaction re-checks is_active +
   lockout under row locks; inactive accounts never silently reactivated.
8. Post-commit dispatch is best-effort on every Google path (sign-in events,
   activation events, activation resend) — never on existing password/2FA/
   signup paths, whose exception contracts are unchanged (observably
   identical: payloads, ordering, conditions, exception propagation, dispatch
   count). Post-commit dispatch failures are caught + diagnostic-logged,
   never turning a committed transaction into an HTTP failure. Pre-commit
   failures stay fatal and rollback-safe. **Per-event isolation: each
   dispatch attempt gets its own try/catch — one event's failure never
   suppresses another.** Google-specific dispatch policy only: the shared
   `dispatchEvents()` is NOT modified, and the Google path never wraps the
   whole call in one try/catch. Best-effort ≠ guaranteed delivery; a
   transactional outbox is the acknowledged deferred reliability improvement.
9. Dispatch matrix (explicit):

   | Identity path | Token/device | Events |
   |---|---|---|
   | Existing active linked identity | Normal `issueToken()` pipeline | Best-effort sign-in events after commit — exactly once |
   | Existing inactive linked identity | None | Activation resend only — no sign-in events |
   | New Gmail / eligible Workspace | `issueToken()` inside the transaction | Best-effort sign-in events after commit — exactly once |
   | New third-party | None | Best-effort activation events after commit |

10. Lockout/resend: activation-OTP resend is **permitted while locked** —
    consistent with the existing password activation endpoint. Lockout is
    the token-issuance boundary (S.4); resend is governed by its own
    60-second throttle, anti-enumeration and 5-minute expiry; a locked user
    who completes activation still cannot obtain a token until the lock
    expires.
11. TOTP 2FA skipped on the Google path (customer-only policy); ID token
    never logged/persisted/in error context; no Google access/refresh tokens
    exist; no Google profile data persisted; `/enter-otp?mode=activation` is
    a UI hint only.

## 3. Verified state (2026-11-14)

| Fact | Evidence |
|---|---|
| `issueToken()` is fully transactional (row re-lock, is_active + lockout checks, device registration, prior-token revocation, token creation; no internal events) and is the lockout boundary | `Modules/Auth/app/Actions/Public/Auth/CompleteAuthenticationAction.php` |
| `dispatchEvents()` is post-commit by contract; fires `UserSignedInEvent` then (conditionally on `deviceIsNew`) `NewDeviceSignInEvent`; notification listeners queued | same file + `Modules/Notification/app/Providers/EventServiceProvider.php` |
| `SignUpAction` runs without a transaction and dispatches events directly (exceptions propagate) | `Modules/Auth/app/Actions/Public/Auth/SignUpAction.php` |
| `requestOtp()` is the throttled (60 s), anti-enumerated resend with **no lockout check**; `storeOtp()` invalidates old + 5-min expiry; `verifyOtp()` marks verified+active | `Modules/Auth/app/Actions/Public/Auth/AbstractAction.php` |
| Super-admin assigned only to seed-time users; signup customers get no roles/permissions | `Modules/Auth/database/seeders/AccessDatabaseSeeder.php:27` |
| GIS `IdConfiguration` supports `nonce` (replay protection) | Google GSI JS reference (`developers.google.com/identity/gsi/web/reference/js-reference`) |
| compose Redis ≥ 7 → atomic GETDEL/Lua available; `REDIS_CLIENT=phpredis` | `docker-compose.yml`, `apps/backend/.env` |

## 4. Backend — `apps/backend/Modules/Auth/`

### 4.1 Deps & config

- `composer require firebase/php-jwt` (root `apps/backend/composer.json`).
- Module config `auth.google`: `enabled` (env `AUTH_GOOGLE_ENABLED`, default
  false), `client_id` (env `AUTH_GOOGLE_CLIENT_ID`), `nonce_ttl` 300.
- Env examples updated: `apps/backend/.env.example`, root `.env.example`,
  `apps/storefront/.env.example`.

### 4.2 Schema / model

- `app/Schemas/SocialAccount/SocialAccountSchema.php` +
  `SocialProviderEnum` ('google' only, internal — never request input).
- Migration `auth_social_accounts`: `id`, `user_id` FK → `auth_users`
  (cascade), `provider` string(32), `provider_user_id` string(191),
  timestamps; **unique(`provider`,`provider_user_id`) AND
  unique(`user_id`,`provider`)**, index via the unique constraints.
  Conventions per the sibling auth migrations.
- `app/Models/SocialAccount.php` (enum-cast provider, no provider tokens);
  `User::socialAccounts()`.
- DB unique constraints remain the authority for races: violations →
  predictable domain errors; the action distinguishes an email collision
  (`email_exists`) from a duplicate social identity (re-resolve) and never
  maps arbitrary DB failures to `email_exists`.

### 4.3 Nonce service

`app/Support/Google/SignInNonceService.php`: `issue()` → 32-byte random
(base64url), stores `sha256(nonce)` in the shared Redis store, TTL 5 min;
`consume($nonce)` → atomic Lua get-and-delete (never `get()`+`forget()`);
fails closed on Redis unavailability (typed `auth.google.unavailable`, 503,
no process-local fallback). Hashed keys. The `google/nonce` endpoint is
rate-limited (`throttle:auth-sign-in`) **and** feature-flag gated — when
disabled it returns the same `auth.google.disabled` as the credential
endpoint.

### 4.4 Verifier

`app/Support/Google/GoogleIdTokenVerifier.php` (+ `GoogleIdentity` DTO,
`GoogleEmailClass` enum) — exact order: ① parse untrusted ② alg RS256-only
+ JWKS key lookup (HTTPS with timeouts + size cap, cached ~6 h; unknown-`kid`
→ single-flight refresh via `Cache::lock()->block(3)` — bounded, released
after use, fails closed → typed error) ③ verify signature ④ claims: `iss` ∈
{accounts.google.com, https://accounts.google.com}; `aud` = client_id; `azp`
(present ⇒ = client_id; multi-`aud` ⇒ required + equal); `exp`, `iat` leeway
(explicit future-`iat` rejection — the JWT library does not check `iat`);
non-empty `sub`, valid `email`; `email_verified` genuine boolean ⑤ require a
non-empty string `nonce` claim ⑥ hash + atomically consume that exact claim
(a request-body nonce is never a substitute) ⑦ then account resolution. No
unverified-decode fallback; the raw token is never logged.

### 4.5 OTP persistence/dispatch split

- `AbstractAction::requestOtp()` refactored minimally into `mintOtp()`
  (lookup + anti-enumeration + throttle check + generate + `storeOtp`, **no
  events**) + the direct `OtpRequestedEvent` dispatch kept in `requestOtp()`
  as default — password-path behavior observably identical.
- `Concerns/IssuesActivationOtp` (SignUpAction + Google):
  `persistActivationOtp()` = throttle check + generate + store (shared,
  fatal; deliberately NO user lookup — signup persists the OTP before the
  user row exists); `dispatchActivationEvents()` = plain `event()`,
  exceptions propagate (the SignUpAction contract); `dispatchActivationEventsBestEffort()`
  = Google-only — `DB::afterCommit()` closure registered inside the
  transaction immediately after OTP persistence, each event in an
  independent try/catch + per-event diagnostic-safe log (rollback ⇒ never
  dispatched); `dispatchOtpRequestedBestEffort()` = Google-only resend
  dispatch (OtpRequestedEvent only).
- `CompleteAuthenticationAction`: event construction extracted into shared
  private helpers; `dispatchEvents()` observably identical for password/2FA
  callers; new Google-only `dispatchEventsBestEffort($user, $context,
  $deviceIsNew)` attempting `UserSignedInEvent` then (still conditional on
  `deviceIsNew`) `NewDeviceSignInEvent`, each in its own try/catch + per-event
  log, same payloads/ordering.
- `SignUpAction` refactor: `persistActivationOtp()` + `dispatchActivationEvents()`
  — observably identical behavior incl. the propagating-dispatch contract.

### 4.6 Action / request / controller / routes

- `Concerns/BuildsSignInContext` extracted from `SignInAction` (device name,
  remember, `X-Cart-Token` — verbatim); `SignInAction` uses it.
- `Actions/Public/Auth/GoogleSignInAction.php`: ① feature flag ② verify
  credential + consume nonce ③ resolve `SocialAccount(google, sub)` → user,
  else candidate by email ④ eligibility, no writes yet: administrative
  predicate → `auth.google.admin_forbidden`; email exists unlinked →
  `auth.google.email_exists` ⑤ per the dispatch matrix (invariant 9) +
  invariant 3 authority; unique-violation races re-resolve once.
- `Http/Requests/Public/Auth/GoogleSignInRequest.php`: `credential` required
  string (min 16, max 4096), optional `device_name`/`remember`;
  `credential` in `$dontFlash`.
- `Http/Controllers/Public/Auth/GoogleSignInAuthController.php`: success →
  `SignInResource` (same envelope as sign-in); `requires_activation` → own
  envelope (2FA-branch pattern). `RequestGoogleNonceAuthController.php`
  (POST, flag-gated, throttled).
- Routes (`routes/api/public.php`): `RouteSchema::SIGN_IN_GOOGLE` =
  `google` + `GOOGLE_NONCE` = `google/nonce`, both `throttle:auth-sign-in`,
  both feature-flag gated. Endpoints:
  `POST /api/v1/auth/public/auth/google`, `POST /api/v1/auth/public/auth/google/nonce`.
- Errors/lang: `auth.google.invalid_token` (401), `nonce_invalid` (401),
  `disabled` (403), `email_unverified` (403), `email_exists` (409),
  `admin_forbidden` (403), `unavailable` (503) + `lang/{en,fa,de,fr,es,ar}/errors.php`.

### 4.7 Tests

`Modules/Auth/tests/Feature/Public/Auth/GoogleSignInTest.php` (28 cases,
fake verifier + real nonce consumption), `GoogleSignInNonceTest.php` (6,
real Redis), `GoogleIdTokenVerifierTest.php` (16, real RSA crypto +
`Http::fake` JWKS). Coverage highlights:

- Feature: Gmail/Workspace instant signup (token works on `/me`); unverified
  third-party email → OTP path; third-party → `requires_activation` with
  zero token/device rows, then existing activation completes and only then
  sign-in yields a token; inactive linked account → resend, no second user;
  `email_exists` → 409, no mutation; administrative rejection → 403 with
  `auth_users`/`auth_social_accounts` unchanged (super-admin, arbitrary
  non-super role, direct permission); locked account → lock error; disabled
  feature (both endpoints); cart merge; one-token-per-device; per-event
  isolation; forced-rollback tests (no token/device/rows survive);
  ordinary-signup exception propagation (pre-extraction contract).
- Nonce: hashed keys, atomic single consumption, unknown/expire/replay
  rejection, multi-instance consumption, Redis-unavailable fail-closed,
  endpoint rate limits.
- Verifier: valid token; bad signature; wrong iss/aud; multi-`aud` × azp;
  expired; future `iat`; missing/malformed sub/email/`email_verified`;
  Gmail domain validation; `hd` absent/empty/malformed/mismatched;
  unknown-`kid` refresh + rotation recovery + JWKS caching;
  JWKS-unavailable fail-closed; alg:none rejection.

## 5. Storefront — `apps/storefront/`

- Env: `NEXT_PUBLIC_GOOGLE_CLIENT_ID` + `NEXT_PUBLIC_AUTH_GOOGLE_ENABLED`
  (both required to render; the backend flag remains the real control).
- `context/auth-context.tsx`: `signInWithGoogle(credential)` — POST
  `/auth/public/auth/google` (+ `X-Cart-Token`) → persist
  `baxela_token`/`baxela_user`, `clearCartToken()`; `requires_activation` →
  routed to `/enter-otp?mode=activation` (UI hint only).
- `components/auth/google-sign-in-button.tsx`: owns the GIS lifecycle —
  `next/script`, `initialize({ client_id, callback, nonce })` + official
  `renderButton`; nonce fetched at init, held in a ref, **cleared on every
  terminal callback path incl. errors**; a failed attempt re-initializes
  with a fresh nonce on retry; ref-guard double render (Strict Mode safe),
  single in-flight callback, unmount cleanup, script-failure +
  `error_callback` (cancelled → silent).
- Pages `app/[locale]/login/page.tsx` + `signup/page.tsx`: divider + button.
- CSP (`next.config.ts`): GIS origins added to `script-src`, `connect-src`,
  `frame-src` **only when the client id + flag are configured**.
- Error UX: distinct, non-leaky toasts (cancelled silent; invalid/expired/
  nonce → retry; `email_exists` → "sign in with your password"; admin →
  generic unavailable; inactive/locked → backend messages). Raw SDK/backend
  errors never shown.
- i18n: keys in `messages/{en,fa,de,fr,es,ar}/shared/common.json`
  (`messages.info.or`, `messages.error.google_*`); RTL-safe markup.

## 6. Definition of done

- [x] Migration + schema + model + relation (auth_social_accounts, dual
      unique constraints).
- [x] Nonce service (atomic, hashed, fail-closed) + flag-gated nonce
      endpoint.
- [x] Verifier (full claim contract, JWKS cache + single-flight rotation
      recovery) + GoogleIdentity classification.
- [x] OTP persistence/dispatch split with per-caller dispatch policy;
      SignUpAction observably identical.
- [x] CompleteAuthenticationAction shared event helpers +
      Google-only best-effort dispatch; dispatchEvents() unchanged.
- [x] GoogleSignInAction (dispatch matrix, eligibility before writes,
      transaction branches, unique-race re-resolution).
- [x] Controllers/routes/requests/errors + 6-locale messages.
- [x] Backend tests: 50 Google cases green; full Auth module suite green on
      **sqlite** (166 passed + 1 pre-existing skip) and on **MySQL**
      (167 passed — the S.4 lockout concurrency test included).
- [x] Storefront: auth context + GIS button + login/signup integration +
      6-locale i18n + CSP origins; `pnpm --filter storefront build` green.
- [x] Docs: this plan, `THIRD_PARTY_SERVICES.md`, `ROADMAP.md` annotation.
- [ ] Owner: E2E with a real Google OAuth client (JS origin
      `http://localhost:3000`) — gmail instant sign-in, third-party OTP
      path, admin rejection, cart merge, Mailpit new-device notification.
- [ ] Owner: production deployment checklist (below).

**Deployment checklist:** prod OAuth client with HTTPS-only authorized JS
origins; `AUTH_GOOGLE_ENABLED`/`AUTH_GOOGLE_CLIENT_ID` consistent with the
storefront's `NEXT_PUBLIC_GOOGLE_CLIENT_ID`/`NEXT_PUBLIC_AUTH_GOOGLE_ENABLED`;
Redis reachable + shared namespace across all app instances (multi-instance
nonce verification); JWKS cache reachable; failure monitoring wired without
credential data.

## 7. Deferred (explicit)

- **Transactional outbox** for post-commit notification delivery (best-effort
  today) and shared-path (password/2FA/signup) notification hardening.
- **Authenticated account linking + "Connect Google" UI** in profile
  settings (v1 rejects email conflicts with guidance to sign in by
  password).
- Other providers (GitHub, Apple) — the GIS/OIDC verifier approach extends
  per-provider.

---

## Change log (review-driven)

- rev 2: replaced auto-linking with the no-linking v1 policy; email
  authority semantics (Gmail/Workspace vs third-party); admin rejection
  before mutation (all roles); transactional guarantees; full verifier
  contract; verifier crypto tests + MySQL runs.
- rev 3: atomic hashed nonce (shared store), strict `hd` validation,
  inactive-Google-account resend lifecycle, explicit eligibility ordering,
  OIDC-precise `azp`, password-flow regression gates.
- rev 4: nonce consumption after cryptographic validation; verified
  transaction anatomy; per-branch transaction contract; Google-only
  dispatch policy (shared `dispatchEvents()` untouched).
- rev 5: per-event isolation; resend persistence-before-dispatch; shared-OTP
  regression contract (return values/exception types/throttle semantics).
- rev 6: dispatch matrix made explicit; resend path under the Google
  best-effort policy; afterCommit registration inside the transaction.
- rev 7: activation-event dispatch best-effort too (pre-commit stays fatal);
  lockout/resend policy decided + tested; nonce endpoint flag-gated.
- rev 8: dispatch policy per caller (SignUpAction keeps propagating
  dispatch); quality gates (diagnostic logging, multi-instance Redis,
  commit discipline).
- rev 9: explicit dispatch matrix + resend ordering.
- rev 10: per-event isolation on sign-in dispatch; shared-OTP regression
  contract strengthened.
- rev 11: third-party `email_verified: false` reaches OTP activation
  (claim validation vs. account policy); invariant-4 wording scoped to
  account/authentication-state mutations.
- rev 12: `hd` classification (absent vs. present-but-empty/malformed);
  unique-violation classification; "byte-identical" → observably identical.
