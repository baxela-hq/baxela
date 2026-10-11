# Logging & Error Monitoring

How logs and error reports are produced, where they live, how long they are
kept, and how to triage an incident. Sentry is **optional**: every app runs
normally with an empty DSN, which keeps the SDK fully disabled — relevant
for self-hosters and CI.

## Purpose separation

| System | Scope | Use it for |
|---|---|---|
| Application logs (Laravel channels, Docker stdout/stderr) | All services | Request activity, diagnostics, warnings, operational events, local debugging |
| Error monitoring (Sentry) | Backend, admin, storefront | Actionable exceptions, stack traces, aggregated/recording failures |
| Operational alerting | Future phase | Incidents requiring action (error spikes, repeated job failures). Use Sentry alerts once volume justifies it |

Logs answer "what happened around this request"; Sentry answers "what is
breaking and for whom". Neither replaces the other.

## Where logs live

| Environment | Backend (Laravel/PHP) | nginx | Frontends |
|---|---|---|---|
| Development | `apps/backend/storage/logs/laravel-YYYY-MM-DD.log` (bind-mounted to host) | Container stdout/stderr (`docker compose logs web`) | Dev-server terminals / `docker compose logs admin storefront` |
| Production | Container stderr (`LOG_CHANNEL=stderr`) | Container stdout/stderr | Container stdout/stderr |

To follow logs in production:

```sh
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f backend queue scheduler
docker compose --env-file .env.production -f docker-compose.prod.yml logs -f --tail 200 backend-nginx
```

## Retention — two independent mechanisms

- **Docker container logs**: every service in both compose files sets the
  `x-logging` anchor (json-file, `max-size: 10m`, `max-file: 3`) — roughly a
  30 MB cap per service of rotated container logs. This governs container
  stdout/stderr **only**.
- **Laravel file logs**: the `daily` channel (dev default via
  `LOG_STACK=daily`) keeps `LOG_DAILY_DAYS=14` days of files in
  `storage/logs/`. Production writes to stderr instead, so no files
  accumulate there. Docker's limit does **not** govern these files, and
  vice versa.

## Request-ID correlation

`Modules/Core/app/Http/Middleware/AssignRequestId.php` gives every API
request a UUID that is:

- added as `request-id` context to every log line written during the
  request,
- returned to the client in the `X-REQUEST-ID` response header,
- set as the `request_id` tag on Sentry events (when the SDK is enabled).

A request ID from a user report therefore matches both log lines and the
Sentry event. Queue workers don't run the middleware, so job events are
untagged — correlate those by job class/timestamp instead.

## Sentry setup

Create one project per app in your Sentry org (e.g. `baxela-backend`,
`baxela-admin`, `baxela-storefront`), and separate projects (or
environments) for staging vs production so staging events can't land in a
production project. Then set the DSNs:

| App | Variable | Read at | Where to set it |
|---|---|---|---|
| Backend | `SENTRY_LARAVEL_DSN` | Runtime | `apps/backend/.env` / root `.env.production` |
| Storefront (browser) | `NEXT_PUBLIC_SENTRY_DSN` | **Build time** | Root env; passed as an image build arg |
| Storefront (server) | `SENTRY_DSN` | Runtime | Root env; storefront service environment |
| Admin (browser) | `VITE_SENTRY_DSN` | **Build time** | Root env; passed as an image build arg |

Build-time variables are inlined into the JS bundle — changing them in a
running deployment requires an image rebuild + redeploy. Runtime variables
apply on container restart. `SENTRY_TRACES_SAMPLE_RATE` (backend) enables
performance tracing; it defaults to `0` (off) and error reporting does not
need it.

The storefront CSP (`next.config.ts`) derives the Sentry ingest origin from
the build-time DSN and allows it in `connect-src` — configuring a DSN
without rebuilding would be blocked by the CSP anyway.

### What is captured

- **Backend**: exceptions that Laravel decides to report. Verified against
  the installed `sentry/sentry-laravel` (4.x): it registers a `reportable`
  callback via `Integration::handles()` in `bootstrap/app.php`, and Laravel's
  `Handler::report()` applies its `shouldntReport` filter **before** any
  reportable runs. The `dontReportWhen` rule there excludes expected 4xx
  `BaseException`s, so ordinary domain outcomes (invalid credentials,
  validation failures) never reach Sentry; 5xx domain failures and
  unexpected exceptions do. This is pinned by
  `Modules/Core/tests/Feature/Sentry/ErrorReportingTest.php`.
- **Storefront**: client errors via the segment `error.tsx` /
  root `global-error.tsx` boundaries (handled errors are captured
  explicitly), server errors via `instrumentation.ts`'s `onRequestError`.
- **Admin**: render errors via the Sentry `ErrorBoundary` around the router;
  query failures via `queryCache.onError`; mutation failures via
  `handleServerError`. The three paths are disjoint (queries vs mutations vs
  render), so a single failure yields at most one event. Expected 4xx
  responses are not reported — only 5xx and network failures.

### Privacy notes

`send_default_pii` is false everywhere. On top of that:

- The admin never serializes the raw `AxiosError` (its `config` carries the
  bearer token): reported errors carry method, sanitized path (query string
  stripped), status, code and a length-capped server title only.
- Backend SQL breadcrumbs exclude bindings
  (`breadcrumbs.sql_bindings => false`).
- `send_default_pii=false` does **not** scrub custom context you attach
  yourself — review any future `captureException` extras before adding
  request bodies, customer data or tenant identifiers.

## Opt-in debug logging (backend)

`CORE_LOG_REQUESTS=true` in `apps/backend/.env` enables two verbose
debuggers (off by default; they are noisy):

- `RequestLogger` — logs every API request/response pair (sensitive keys
  redacted, response truncated to 1000 chars) to the default channel.
- `LogAllEvents` — logs every fired domain event (redacted).

## Failed jobs

The database queue records failures in the `failed_jobs` table. Inspect
with:

```sh
php artisan queue:failed          # list
php artisan queue:failed-table    # (migration helper, already applied)
php artisan queue:retry {uuid}    # retry one
php artisan queue:flush           # drop all
```

Automated alerting for repeated failures is a tracked follow-up (below).

## Staging smoke tests (after configuring DSNs)

Run these against a staging environment, not production customers:

1. **Backend 500** — trigger an unexpected exception → exactly one event in
   the backend project, tagged with the `X-REQUEST-ID` the client received.
2. **Backend expected 4xx** — e.g. wrong OTP → no Sentry event.
3. **Storefront client** — throw in a client component → one event with
   browser context; the localized error page renders.
4. **Storefront server** — throw during SSR → one event with server context
   (via `onRequestError`); `global-error.tsx` renders for root failures.
5. **Admin render** — throw in a component → one event; the boundary
   fallback renders (also works with Sentry disabled).
6. **Admin 5xx** — simulate an API 500 → one event carrying method/path/
   status only (no headers, no body).
7. **Disabled check** — unset all DSNs → apps start, serve, and test green
   with no monitoring-related errors.

## Deferred follow-ups

- Source-map upload for minified frontend stack traces (needs a Sentry auth
  token; `withSentryConfig` currently has `sourcemaps.disable: true`).
- Release tagging (`SENTRY_RELEASE`) for error-to-deploy correlation.
- Performance tracing (set `SENTRY_TRACES_SAMPLE_RATE` > 0).
- Sentry alerts on error spikes; failed-job alerting.
