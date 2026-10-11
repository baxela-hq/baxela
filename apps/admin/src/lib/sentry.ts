import * as Sentry from '@sentry/react'

const dsn = import.meta.env.VITE_SENTRY_DSN

// Empty DSN (the default) leaves the SDK fully disabled — dev, CI and
// self-hosters run without a Sentry account. VITE_SENTRY_DSN is baked into
// the bundle at build time, so changing it in prod requires a rebuild.
//
// Sentry v11 collects identifying data by default; these flags restore the
// old sendDefaultPii=false behavior (no user info, cookies, headers,
// bodies or query params on events).
if (dsn) {
  Sentry.init({
    dsn,
    environment: import.meta.env.MODE,
    dataCollection: {
      userInfo: false,
      cookies: false,
      httpHeaders: false,
      httpBodies: [],
      urlQueryParams: false,
    },
  })
}

export { Sentry }
