# Security Roadmap — hardening the trust surface

> **Purpose of this document:** Register and sequence the security gaps
> found in the 2026-11-10 platform review, plus a verified audit of the
> existing security surface. Security is a separate initiative from the
> platform-hardening gaps (`docs/PLATFORM_HARDENING_ROADMAP.md`) because
> it has a different urgency profile, different review discipline (every
> slice should get an adversarial pass, not just a code review), and
> different success criteria (what *didn't* happen, not what did).
>
> **Created:** 2026-11-10 · **Owner:** solo maintainer · **Status:** register
> complete; slices S.1–S.4 ordered by attack impact
>
> **How to use:** Phases strictly in order **S.1 → S.2 → S.3 → S.4**. Each
> slice gets a detailed plan doc (`docs/plans/security-*.md`) before
> execution, with an adversarial review pass. Re-verify ⚠-marked facts.

---

## 1. Verified security surface (2026-11-10)

What exists and works today — the foundation these slices build on:

| Control | Status | Evidence |
|---|---|---|
| Rate limiting on auth endpoints | ✅ | `throttle:auth-{sign-up,sign-in,otp-request,otp-verify,otp-lockout}` on every public Auth route |
| OTP attempt cap | ✅ | `OtpCodeSchema::MAX_ATTEMPTS` enforced in `AbstractAction:132`; codes expire |
| Password policy | ✅ | `Password::default()` (Laravel's bundled rules: min 8, letters+numbers) on sign-up, admin create, password reset |
| Sanctum token expiry | ✅ | `SignInAction:56` creates tokens with `now()->addDays($ttl)`; one-token-per-device (old token deleted on re-login) |
| Admin route protection | ✅ | Every `api/v1/{module}/admin/...` route behind `PermissionMiddleware` + `auth:sanctum` (launch-readiness Part 1 verified, test enforced) |
| Webhook signature verification | ✅ | All 8 payment drivers verify HMAC/header signatures before settling |
| SSRF protection on outbound webhooks | ✅ | Private-IP rejection at create + re-validation at delivery (C.2) |
| Secrets encrypted at rest | ✅ | Webhook endpoint secrets follow the encrypted-at-rest pattern |
| Machine token management | ✅ | Admin-scoped CRUD for server-to-server tokens with abilities + expiry |
| Audit logging | ✅ | Audit module logs admin actions (order updates, settings changes, etc.) |

What's absent — the gaps this roadmap addresses:

| Gap | Attack scenario | Impact |
|---|---|---|
| **No 2FA on admin** | Compromised password = full admin access (refunds, orders, settings) | Critical — single factor on money operations |
| **No session revocation** | A leaked token stays valid until natural expiry; no "log out all devices" | High — a stolen admin token can't be killed |
| **No security headers** | No CSP, no HSTS, no X-Frame-Options; XSS and clickjacking surface | Medium — the API-only architecture limits XSS surface, but the admin panel serves HTML |
| **No account lockout on admin sign-in** | Throttled but not locked; a slow-drip brute-force runs indefinitely | Medium — rate limiting slows but doesn't stop |
| **No login anomaly detection** | Sign-in from a new country/IP generates no alert | Low for v1 (single-admin stores), rises with team accounts |

---

## 2. Phase S.1 — Two-factor authentication on admin accounts (~2 d)

### Design decisions (to be finalized in the slice plan)

1. **TOTP (RFC 6238), not SMS.** Time-based one-time passwords via
   `pragmarx/google2fa` (or equivalent pure-PHP library — no external
   service, shared-hosting safe). QR code enrollment rendered as a data
   URI (no CDN dependency).
2. **Opt-in per user, enforced for admins above a configurable role.**
   Default: super-admins must enroll on next sign-in. Regular admins:
   prompted but not forced until the store owner flips a setting.
3. **Sign-in flow change:** password → if 2FA enabled → return
   `{two_factor_required: true, challenge_token}` → client shows OTP
   input → `POST /auth/2fa/verify` with challenge_token + code → tokens.
   The challenge token is short-lived (2 min) and single-use.
4. **Recovery codes:** 8 one-time codes generated at enrollment, shown
   once, hash-stored. Each code is single-use. "Regenerate codes" is an
   admin-profile action.
5. **Enrollment:** admin-profile settings page → "Enable 2FA" →
   QR + secret shown → user confirms with one valid code → enabled.
   Disenrollment requires a valid code (not just the password).

### Work items

- [ ] `two_factor_secret` (encrypted) + `two_factor_recovery_codes` (hashed)
      + `two_factor_confirmed_at` columns on users.
- [ ] `pragmarx/google2fa` (or equivalent) + QR-code generation.
- [ ] `EnableTwoFactorAction`, `ConfirmTwoFactorAction`, `DisableTwoFactorAction`.
- [ ] Sign-in flow: challenge-token bridge between password and token issue.
- [ ] Recovery-code generation + verification (single-use, hash-stored).
- [ ] Admin API: enable/confirm/disable/regenerate-recovery endpoints.
- [ ] Admin UI: 2FA settings section (QR enrollment, recovery codes).
- [ ] Tests: enrollment → sign-in with code; wrong code; expired challenge;
      recovery code single-use; forced enrollment for super-admins;
      disenrollment requires valid code.

### Edge cases

- User loses device + recovery codes → admin reset flow (another admin
  or the super-admin can disable 2FA, audit-logged).
- Clock drift → the library's default ±1 window is sufficient; no custom
  tolerance.
- Concurrent sign-in attempts with different challenge tokens → only the
  latest is valid.

---

## 3. Phase S.2 — Session & token revocation (~1 d)

### Design decisions

1. **"Sign out all devices":** an admin-profile action that deletes all
   of the user's Sanctum tokens (the existing one-token-per-device model
   makes this a single query). The current session's token is re-issued.
2. **Token listing with revocation:** extend the existing machine-token
   admin surface to also list and revoke *user* tokens (name, device,
   last_used_at, created_at) — today only server-to-server tokens are
   visible.
3. **Forced revocation by a super-admin:** for team stores — a super-admin
   can revoke any user's sessions (e.g., offboarding a staff member).
   Audit-logged.
4. **No refresh-token rotation in v1:** Sanctum's opaque tokens with
   per-device expiry are sufficient for the threat model (single-store,
   small-team deployments). JWT refresh flows add complexity without
   adding security for this architecture.

### Work items

- [ ] `RevokeAllTokensAction` (admin-profile + super-admin variant).
- [ ] Token listing: user tokens alongside machine tokens, with revocation.
- [ ] Super-admin forced revocation endpoint + audit log.
- [ ] Admin UI: "Active sessions" section under the profile.
- [ ] Tests: revoke-all invalidates every session; current session
      re-issued; super-admin revokes another user's tokens; audit entry.

---

## 4. Phase S.3 — Security headers & transport hardening (~0.5 d)

### Design decisions

1. **Headers on the API:** the API serves JSON only, which limits XSS
   surface — but the headers cost nothing and protect against
   content-sniffing:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
     (only when the request arrived over HTTPS)
   - `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`
2. **Headers on the admin panel (nginx):** the admin serves HTML with
   inline scripts (React SPA) — a strict CSP requires nonce/hash work
   that's not worth v1. Ship the cheap headers:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `Referrer-Policy: strict-origin-when-cross-origin`
3. **Headers on the storefront (Next.js):** `next.config.ts` `headers()`
   — same set plus `Permissions-Policy: camera=(), microphone=(), geolocation=()`.
4. **No CORS middleware changes:** the API is consumed by first-party
   frontends; the default (same-origin) is correct until a third party
   needs access.

### Work items

- [x] API: a `SetSecurityHeaders` middleware on the `api` group (CSP +
      conditional HSTS), plus the exception-render hook so unmatched
      `/api/*` JSON 404 envelopes carry the headers too.
- [x] Admin nginx: headers were already present (server-level baseline,
      repeated in the locations that declare their own `add_header`) —
      no change needed. The ownership split keeps nginx as the source
      for static files, nginx error pages, and Reverb responses.
- [x] Storefront: `next.config.ts` headers block — already shipped.
- [x] Trust boundary: a config-driven `TrustProxies` middleware
      (`core.trusted_proxies` → `env('TRUSTED_PROXIES', '')`, default
      trusts nobody) honoring only `X-Forwarded-For` + `X-Forwarded-Proto`,
      and a dedicated compose `proxy` network (subnet 172.28.0.0/29)
      carrying only the nginx↔fpm path — so a container peer can never
      feed authoritative forwarding headers to the app. Never set
      `TRUSTED_PROXIES=*` on the shared compose network.
- [x] Tests: CSP on success / matched-route 422 / unmatched 404 envelopes;
      HSTS only when genuinely secure (direct TLS or a trusted proxy's
      forwarded proto — spoofed and untrusted-peer forwarded headers
      rejected); `Retry-After` preserved on 429; PDF downloads intact;
      CSP absent off `/api/*`.

**Deployment prerequisites (2026-11-12):** set
`TRUSTED_PROXIES=172.28.0.0/29` in the backend env (matches the compose
`proxy` network subnet); the edge proxy must overwrite (not forward)
client-supplied `X-Forwarded-Proto`/`X-Forwarded-For`; every subdomain
must serve HTTPS before the `includeSubDomains` HSTS policy reaches
browsers; verify HSTS through the real production edge, not just
feature tests.

---

## 5. Phase S.4 — Admin sign-in lockout & anomaly awareness (~0.5 d)

**Status: implemented (2026-11-12)** — see
`docs/plans/security-s4-lockout-notifications.md` (7 review rounds).
**Deviation from decision 1, adopted through review:** the lockout state
lives in a DB table (`auth_sign_in_lockouts`, keyed by a SHA-256 of the
canonical email), not in cache — a cache counter cannot serialize against
token issuance (the failed-password path and the token-issuance boundary
must share one row lock for "locked account never completes
authentication" to hold). Device memory is likewise a persistent
`auth_user_devices` table, not the per-token names (sign-out and forced
revocation delete tokens, which would re-fire the notification for the
same device). Device names are client-reported labels — the notification
never claims verified identity. The new-device notification is registered
as a locked (non-disableable) notification code.

### Design decisions

1. **Progressive lockout:** after N failed sign-in attempts (config,
   default 5), the account is locked for M minutes (config, default 15).
   Rate limiting throttles by IP; lockout is by account — they compose.
   ~~The lockout is stored in cache (not a DB column) with a TTL.~~ →
   stored in the DB with a `locked_until` timestamp; the inactivity-reset
   window of the failure counter equals `lockout_minutes`.
2. **New-device sign-in notification:** when a user signs in and the
   device name differs from any previously seen device (persistent
   device table), fire a database + email notification: "New sign-in from
   [device]". Uses the existing Notification module; no new channels.
3. **No IP geolocation in v1:** external GeoIP services are a privacy
   and dependency concern; the device-name signal is sufficient for the
   "was this you?" prompt.

### Work items

- [x] Lockout check + counter on the sign-in action and the
      token-issuance boundary, serialized on the lockout-row lock
      (DB-backed, replacing the original cache-only design).
- [x] `NewDeviceSignInEvent` + Notification listener (email + database,
      locked code) + persistent `auth_user_devices` registration.
- [x] Config: `auth.lockout.{max_attempts,lockout_minutes}`.
- [x] Tests: lockout triggers after N failures; clears after M minutes;
      never extends under pressure; concurrency (two-connection MySQL
      bootstrap + forced-interleave cases); new-device notification fires
      on first use only and cannot be disabled; all three completion
      paths (password, 2FA verify, forced enrollment) refuse locked
      accounts.

---

## 6. Policies this roadmap adds

1. **Admin access is two-factor.** Any account with refund/order/settings
   permissions enrolls 2FA — no exceptions. The super-admin role is
   force-enrolled at upgrade time.
2. **Every token is revocable.** No credential, human or machine, is
   irrevocable by design. The admin surface exposes every active session.
3. **Headers are a deployment default, not a per-site choice.** Every
   deploy target (prod compose, shared hosting, local dev) ships the
   same header floor.
4. **Security slices get adversarial review.** Each phase's plan doc gets
   one external review specifically looking for bypass paths — not just
   a code review looking for bugs.

## 7. Explicit non-goals

Web Application Firewall (WAF) — infrastructure-layer, deploy-target-specific.
Penetration testing — external engagement, not a code slice. SMS-based 2FA —
carrier dependency, cost, SIM-swap risk; TOTP is strictly better for this
architecture. JWT/auth rotation — Sanctum opaque tokens are sufficient
for the threat model. Content-Security-Policy nonces on the admin SPA —
deferred until the admin panel's build pipeline supports them cleanly.

## 8. Related docs

- `docs/PLATFORM_HARDENING_ROADMAP.md` — non-security platform gaps (H–L)
- `docs/plans/launch-readiness.md` — the launch gate that already verified
  rate limiting, password policy, admin route protection, and the secrets
  audit
- `apps/backend/AGENTS.md` — module conventions the security slices must
  respect
