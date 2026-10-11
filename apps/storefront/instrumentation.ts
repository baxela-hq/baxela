import * as Sentry from "@sentry/nextjs";

/**
 * Server-side Sentry bootstrap. Next.js runs register() once per runtime
 * before handling requests: the Node runtime (SSR, route handlers) and the
 * Edge runtime (proxy.ts, the next-intl locale middleware).
 *
 * SENTRY_DSN is runtime-only (compose env); without it the SDK stays inert
 * and onRequestError below is a no-op.
 */
async function initSentry() {
  const dsn = process.env.SENTRY_DSN ?? process.env.NEXT_PUBLIC_SENTRY_DSN;

  if (dsn) {
    Sentry.init({
      dsn,
      environment: process.env.NODE_ENV,
      // Sentry v11 collects identifying data by default; these flags
      // restore the old sendDefaultPii=false behavior.
      dataCollection: {
        userInfo: false,
        cookies: false,
        httpHeaders: false,
        httpBodies: [],
        urlQueryParams: false,
      },
    });
  }
}

export async function register() {
  if (
    process.env.NEXT_RUNTIME === "nodejs" ||
    process.env.NEXT_RUNTIME === "edge"
  ) {
    await initSentry();
  }
}

/** Captures errors from nested server components / route handlers. */
export const onRequestError = Sentry.captureRequestError;
