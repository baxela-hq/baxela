import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const API_ORIGIN = (() => {
  try {
    return new URL(
      process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:3000"
    ).origin;
  } catch {
    return "http://localhost:3000";
  }
})();

// Reverb websocket origin (http in dev, https/wss behind the edge proxy).
const REVERB_SCHEME = process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https";
const REVERB_HOST = process.env.NEXT_PUBLIC_REVERB_HOST;
const REVERB_PORT = process.env.NEXT_PUBLIC_REVERB_PORT ?? "443";
const REVERB_ORIGIN = REVERB_HOST
  ? `${REVERB_SCHEME === "https" ? "wss" : "ws"}://${REVERB_HOST}${
      [80, 443].includes(Number(REVERB_PORT)) ? "" : `:${REVERB_PORT}`
    }`
  : "";

const isProduction = process.env.NODE_ENV === "production";

// Backend-served product/category images may live on any https host, and on
// the (dev) API origin.
const imgSources = isProduction
  ? ["'self'", "data:", "https:", API_ORIGIN]
  : ["'self'", "data:", "http:", "https:"];

// Pragmatic first-step CSP: scripts keep 'unsafe-inline' because Next.js
// bootstraps with inline scripts unless nonce-based CSP is wired through
// middleware; tightening that is a tracked follow-up. The CSP's real value
// today is constraining connect/img/frame targets under a stolen token.
const contentSecurityPolicy = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'`,
  `style-src 'self' 'unsafe-inline'`,
  `img-src ${imgSources.join(" ")}`,
  "font-src 'self' data:",
  `connect-src 'self' ${API_ORIGIN} ${REVERB_ORIGIN}`.trimEnd(),
  "object-src 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
  // Dev talks to the API over plain http (compose publishes :8085 without
  // TLS), so upgrade only in production.
  ...(isProduction ? ["upgrade-insecure-requests"] : []),
].join("; ");

const securityHeaders = [
  { key: "Content-Security-Policy", value: contentSecurityPolicy },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
  // The storefront is served behind a TLS-terminating edge proxy; HSTS is
  // set here so it reaches the browser through that proxy.
  {
    key: "Strict-Transport-Security",
    value: "max-age=31536000; includeSubDomains",
  },
];

const nextConfig: NextConfig = {
  // Trace the server into .next/standalone for the production Docker image
  // (infrastructure/docker/production/storefront/Dockerfile runs server.js).
  output: "standalone",

  // Any host on the LAN subnet, so phones/other machines can load dev assets
  // from the dev server (Next blocks cross-origin dev resources by default).
  // Wildcards match one dot-segment each, so this survives DHCP IP changes.
  allowedDevOrigins: ["192.168.*.*"],

  async headers() {
    return [
      {
        source: "/:path*",
        headers: securityHeaders,
      },
    ];
  },
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
