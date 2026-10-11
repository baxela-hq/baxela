import * as Sentry from "@sentry/nextjs";

/**
 * Browser-side Sentry bootstrap, loaded by Next before any app code.
 * NEXT_PUBLIC_SENTRY_DSN is baked in at build time — changing it requires a
 * rebuild (unlike the server-side SENTRY_DSN, which is runtime). An empty
 * DSN leaves the SDK fully disabled.
 */
const dsn = process.env.NEXT_PUBLIC_SENTRY_DSN;

// Sentry v11 collects identifying data by default; these flags restore the
// old sendDefaultPii=false behavior (no user info, cookies, headers,
// bodies or query params on events).
if (dsn) {
  Sentry.init({
    dsn,
    environment: process.env.NODE_ENV,
    dataCollection: {
      userInfo: false,
      cookies: false,
      httpHeaders: false,
      httpBodies: [],
      urlQueryParams: false,
    },
  });
}
