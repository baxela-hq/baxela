# Plan S.3 — Security headers & transport hardening

> **Purpose of this document:** Self-contained execution plan for Phase S.3
> of `docs/SECURITY_ROADMAP.md`. This is a small slice by design: nginx and
> the storefront already carry their share of the headers; Laravel adds
> exactly the two missing API headers behind an explicit trust boundary.
> **The standing invariant: an attacker-supplied `X-Forwarded-Proto` must
> never activate HSTS unless it arrives through a proxy the deployment has
> declared trusted. Security headers must appear on every `/api/*` response
> — success, rendered error envelopes, and unmatched-route 404s alike.**
>
> **Created:** 2026-11-12 · **Revised:** 2026-11-12 — rev 1 (review 1,
> 7.5/10 — nginx inheritance misdiagnosis corrected, ownership split
> adopted), rev 2 (review 2, 9.0/10 — Symfony response type, trusted-proxy
> handling, deterministic tests), rev 3 (this document, pending review);
> see §8 · **Owner:** solo maintainer · **Status:** rev 3, awaiting review
>
> **How to use:** Re-verify ⚠-marked facts before relying on them.
> Conventional commits; never commit unless asked; heavy commands
> `nice -n 10`; Larastan + Pint before declaring done. **Budget: 0.5 d.**

---

## 1. Where this fits

The roadmap (§4) assigns cheap, high-value headers to three surfaces. The
verified current state:

| Surface | Status in this slice |
|---|---|
| **Storefront** | **Already complete — untouched.** `apps/storefront/next.config.ts` ships CSP (dynamically derived from API/Reverb/Sentry origins), `nosniff`, `DENY`, Referrer-Policy, Permissions-Policy, HSTS via `headers()`. |
| **nginx** (admin, backend prod, backend dev) | **Already correct — untouched.** All three confs set the baseline (`X-Content-Type-Options`, `X-Frame-Options`, Referrer-Policy) at server level. Locations declaring their own `add_header` (`/assets/`, `= /index.html`) correctly repeat them; locations without any inherit them. nginx keeps covering static files, nginx-generated errors, and Reverb. |
| **Laravel API** | **The gap.** Nothing sets `Content-Security-Policy` or `Strict-Transport-Security`. This slice adds exactly those two, and the proxy-trust layer HSTS requires. |

## 2. Verified state (2026-11-12)

| Fact | Evidence |
|---|---|
| Framework | Laravel 13.38 — no `app/Http/Kernel.php`; middleware config in `bootstrap/app.php`, whose `withMiddleware` closure is **empty** (first content lands with this slice) |
| Exception rendering | `bootstrap/app.php` `$exceptions->render()` — gates on `expectsJson()`, builds the JSON envelope, preserves protocol headers via `withHeaders($e->getHeaders())` (e.g. `Retry-After`) |
| Matched-route exceptions | Laravel's routing pipeline (`Router::runRouteWithinStack` → `Pipeline::handleException`) renders them **inside** the middleware chain — api-group middleware decorates those responses. Only **unmatched** routes (404) bypass route middleware. |
| House middleware pattern | `Modules/Core/app/Http/Middleware/AssignRequestId` — no constructor, `public const string` constants, header set after `$next()`; registered via `$router->pushMiddlewareToGroup('api', ...)` in `CoreServiceProvider::boot()` |
| Middleware test home | `Modules/Core/tests/Feature/Http/Middleware/` (existing `IdempotencyMiddlewareTest`) |
| Rate limiter | `Modules/Auth/app/Providers/AuthServiceProvider.php:112` — `RateLimiter::for('auth-sign-in', ... Limit::perMinute((int) config('auth.rate_limit.sign_in'))->by($request->ip()))`; config read **per request** (inside the closure), so test-time `config()` overrides are deterministic |
| Rate config | `Modules/Auth/config/config.php` — `'sign_in' => env('AUTH_SIGN_IN_RATE_LIMIT', 5)` |
| Network topology | `docker-compose.prod.yml` publishes only 8080 (backend-nginx), 8081 (admin), 8082 (storefront). fpm :9000 is **never published** — the app container is reachable only through the backend-nginx container on the Docker network |
| PDF responses | `Storage::download()` (e.g. `DownloadInvoicePdfAction`) → `Content-Disposition: attachment` + `application/pdf` — unaffected by CSP; helper must accept `BinaryFileResponse` |
| Error envelope class | `response()->json(...)` → `Illuminate\Http\JsonResponse` — helper must accept it (not just `Illuminate\Http\Response`) |
| CORS | `config/cors.php` + core `HandleCors` in the global stack, runs before the api group — untouched |
| HTTPS in tests | No precedent — this slice introduces `withServerVariables(['HTTPS' => 'on'])` |
| Quality gates | `composer test`, `composer analyse` (Larastan level 6 + baseline, zero new errors), `vendor/bin/pint` — per `apps/backend/AGENTS.md` |
| ⚠ `Middleware::replace()` | Standard Laravel 11+ API for swapping a global middleware entry; **verify exact signature against the installed framework as implementation step 0** (fallback: `prepend()` the subclass and strip the default) |
| ⚠ `proxies()` override | `Illuminate\Http\Middleware\TrustProxies` exposes a protected `proxies()` hook; **verify it exists in the installed version** before relying on the subclass in D5 |

## 3. Design decisions

### Header ownership

| # | Decision | Why |
|---|---|---|
| D1 | **Ownership split — nobody emits headers twice.** nginx keeps `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy` (all three confs untouched). Laravel adds **only** `Content-Security-Policy` and conditional `Strict-Transport-Security` on `/api/*`. Edge proxy remains the preferred domain-wide HSTS location (deployment note). | nginx keeps covering static files, nginx-generated errors, and Reverb; no duplicate headers on PHP responses; smallest defensible diff. |
| D2 | **CSP value: `default-src 'none'; frame-ancestors 'none'`** — always on `/api/*` responses. | Roadmap §4 value. Safe for a JSON API; PDFs are `attachment` downloads, unaffected by CSP. |
| D3 | **HSTS value: `max-age=31536000; includeSubDomains` — emitted only when `$request->secure()`.** Never a raw `X-Forwarded-Proto` read (see D5). | Browsers ignore STS over plain HTTP, but a spoofed forwarded header on a genuinely-HTTPS connection WOULD be honored — the trust boundary must be explicit, not heuristic. |

### Trust boundary

| # | Decision | Why |
|---|---|---|
| D5 | **Config-driven TrustProxies subclass** (`Modules/Core/app/Http/Middleware/TrustProxies`): overrides `proxies()` to return `config('core.trusted_proxies')` → `env('TRUSTED_PROXIES', '')`. **Default trusts nothing** — a spoofed forwarding header can never flip `secure()`. Deployments set `TRUSTED_PROXIES=*`, which is correct here because fpm :9000 is never published and is reachable only through backend-nginx. Swapped into the global stack via `Middleware::replace()` (⚠ step 0). | The stock middleware takes its IP list once at boot — untestable at runtime. A per-request config read makes the trust boundary explicit, env-switchable per deployment, and testable (spoof vs trusted). Side benefit: today the app ignores the edge's forwarding headers entirely, so generated URLs are `http://` behind TLS; configuring trust fixes scheme correctness app-wide. |

### Coverage and mechanics

| # | Decision | Why |
|---|---|---|
| D6 | **Two hooks, one helper.** (a) `SetSecurityHeaders` on the `api` group covers successes AND matched-route rendered exceptions (pipeline behavior, verified in §2); (b) the exception `render()` closure covers **unmatched** `/api/*` 404s, which bypass route middleware. Both call the same static helper. | Complete `/api/*` coverage without touching responses nginx or other groups produce. |
| D7 | **Scoped to `/api/*` only.** The render-closure hook guards with `$request->is('api/*')`; the middleware is inherently scoped by the group. `/up` health checks and non-API JSON (e.g. `routes/web.php` `/`) never receive the API CSP. | API-specific CSP must not leak onto unrelated surfaces. |
| D8 | **Helper contract — idempotent, per-header, request-aware.** `public static function apply(SymfonyResponse $response, Request $request): SymfonyResponse` using `Symfony\Component\HttpFoundation\Response` (accepts `JsonResponse` + `BinaryFileResponse`). Sets headers individually via `$response->headers->set()` — never replaces the collection; `Retry-After`, `Content-Disposition`, `Content-Type`, `Cache-Control` are preserved. Both hooks may process one response without duplication. | The PDF download path and throttle envelopes must survive intact; `set()` replaces a same-named header, so double application is a no-op. |

### Explicitly out of scope

| # | Decision | Why |
|---|---|---|
| D9 | No CORS changes; no admin SPA CSP/nonce work; no admin `Permissions-Policy` (not in roadmap — and `location = /index.html` wouldn't inherit it; separate decision if ever wanted); no TLS/edge configuration in-repo. | Roadmap §4 boundary + keep the slice small. |
| D10 | **Deterministic throttle tests**: tests set `config(['auth.rate_limit.sign_in' => 2])` rather than counting on the env default. | The limiter closure evaluates config per request (§2) — the override is deterministic regardless of `.env`. |

## 4. Implementation work items

| # | Item | File |
|---|---|---|
| W0 | ⚠ Verify `Middleware::replace()` signature + `TrustProxies::proxies()` hook against installed framework (fallback documented in §2) | — |
| W1 | `TrustProxies` subclass + `'trusted_proxies' => env('TRUSTED_PROXIES', '')` in core config + `$middleware->replace(...)` — first content in the `withMiddleware` closure | `Modules/Core/app/Http/Middleware/TrustProxies.php` (new), `config/core.php` or module config, `bootstrap/app.php` |
| W2 | `SetSecurityHeaders` middleware — `AssignRequestId` house style; constants for both headers/values; `handle()` → `static::apply($response, $request)` after `$next()` | `Modules/Core/app/Http/Middleware/SetSecurityHeaders.php` (new) |
| W3 | Register on the api group beside `AssignRequestId` | `Modules/Core/app/Providers/CoreServiceProvider.php` |
| W4 | Render-closure hook after the existing `withHeaders($e->getHeaders())`: `if ($request->is('api/*')) { $response = SetSecurityHeaders::apply($response, $request); }` | `bootstrap/app.php` |
| W5 | Tests (§5) | `Modules/Core/tests/Feature/Http/Middleware/SecurityHeadersTest.php` (new) |
| W6 | Tick S.3 work items + deployment prerequisites in the roadmap | `docs/SECURITY_ROADMAP.md` |

**Touched: 2 new backend classes, 2 edited backend files, 1 config line, 1 new test file, 1 doc. Zero infrastructure, storefront, or CORS changes.**

## 5. Test plan — `SecurityHeadersTest.php`

Pest + `RefreshDatabase`; house assertion style `expect($response->headers->get(...))`;
`getJson()` used where `Accept: application/json` matters (the render closure's gate).

| # | Test | Proves |
|---|---|---|
| T1 | Factory-user sign-in → 200: CSP present, HSTS **absent** (plain HTTP) | Happy path + HSTS default-off |
| T2 | Same via `$this->withServerVariables(['HTTPS' => 'on'])` → HSTS present | Real `$request->secure()` path |
| T3 | Default trust-none config + `X-Forwarded-Proto: https` → HSTS **absent** | **Spoofed forwarding header cannot activate HSTS** |
| T4 | `config(['core.trusted_proxies' => '*'])` + `X-Forwarded-Proto: https` → HSTS present | Trusted-proxy path activates HSTS |
| T5 | Empty-body sign-in → 422 envelope carries CSP | Matched-route exception through middleware (routing-pipeline path) |
| T6 | `getJson('/api/v1/definitely/not/a/route')` → 404 envelope carries CSP | Unmatched-route path through the render closure |
| T7 | On T5's response: `count($response->headers->all('Content-Security-Policy')) === 1` | Idempotence when both hooks process one response |
| T8 | `config(['auth.rate_limit.sign_in' => 2])`, 3rd sign-in → 429 keeps `Retry-After` **and** carries CSP | Protocol-header preservation + D10 |
| T9 | `getJson('/')` (web route, JSON body) → CSP **absent** | D7 scoping |
| T10 | Invoice PDF download → `application/pdf` + `Content-Disposition: attachment` preserved, CSP present | Binary response compatibility (D8/F1 regression) |

## 6. Verification

1. `nice -n 10 sh scripts/test.sh Modules/Core/tests` — new suite green
2. Full backend suite once — guards interactions (2FA `no-store`, idempotent replays, throttle headers)
3. `composer analyse` — Larastan level 6, zero new errors (baseline absorbs pre-existing debt)
4. `vendor/bin/pint --test` on changed files
5. Single commit: `feat(backend/core): security headers middleware for API responses (S.3)` — sequential security-slice discipline like S.1/S.2

## 7. Deployment prerequisites (documented in the roadmap tick, W6)

1. **`TRUSTED_PROXIES=*`** in production env — fpm is only reachable through backend-nginx; without it HSTS never activates behind the TLS-terminating edge.
2. The **edge proxy must overwrite** (not forward) client-supplied `X-Forwarded-Proto` and friends — defense in depth behind D5.
3. **Every subdomain must serve HTTPS** before `includeSubDomains` HSTS reaches browsers — a forgotten subdomain becomes browser-inaccessible once pinned.
4. Final HSTS verification **through the real production edge**, not only feature tests.

## 8. Review log

| Rev | Reviewer verdict | Key changes |
|---|---|---|
| 1 | 7.5/10 — direction approved, plan not | nginx inheritance misdiagnosis withdrawn (rule had been applied backwards); ownership split adopted (nginx keeps baseline, Laravel adds only CSP+HSTS); exception-flow rationale corrected; admin `Permissions-Policy` dropped |
| 2 | 9.0/10 — "one small final revision, then approve" | Symfony `Response` base type; HSTS derives solely from `$request->secure()` behind configured trusted proxies (no raw forwarded-header reads); spoofing tests added; `withServerVariables()` verified API; `Content-Disposition` terminology; deterministic throttle config |
| 3 | pending | Incorporates all six rev-2 corrections: F1 (Symfony type), F2 (D5 TrustProxies layer), spoofing tests (T3/T4), F4 (withServerVariables + observable assertion), F5 (terminology), F6 (T8 config override) |
