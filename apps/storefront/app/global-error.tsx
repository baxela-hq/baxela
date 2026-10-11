"use client";

import { useEffect } from "react";
import * as Sentry from "@sentry/nextjs";

/**
 * Last-resort boundary: renders when the root layout itself fails, so it
 * must be fully self-contained — its own html shell, inline styles only, no
 * providers, no i18n, no globals.css (mirroring app/global-not-found.tsx,
 * with both UI languages inline since no locale param exists here).
 * Handled errors don't reach global handlers, so capture explicitly.
 */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    Sentry.captureException(error);
  }, [error]);

  return (
    <html lang="en">
      <body
        style={{
          display: "flex",
          minHeight: "100vh",
          flexDirection: "column",
          alignItems: "center",
          justifyContent: "center",
          gap: "1rem",
          margin: 0,
          padding: "0 1.5rem",
          textAlign: "center",
          fontFamily: "system-ui, sans-serif",
          backgroundColor: "#0a0a0a",
          color: "#fafafa",
        }}
      >
        <p style={{ fontSize: "1.25rem", fontWeight: 600, margin: 0 }}>
          Something went wrong · مشکلی پیش آمد
        </p>
        <button
          onClick={() => reset()}
          style={{
            padding: "0.5rem 1rem",
            borderRadius: "0.375rem",
            border: "1px solid #525252",
            backgroundColor: "#171717",
            color: "#fafafa",
            fontSize: "0.875rem",
            cursor: "pointer",
          }}
        >
          Try again · تلاش دوباره
        </button>
      </body>
    </html>
  );
}
