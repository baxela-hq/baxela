// Absolute origin of the storefront as crawlers see it. Server-only
// (sitemap.ts / robots.ts / metadataBase), so a runtime env var works
// with the standalone output — no build-time baking needed.
export const SITE_URL = (
  process.env.SITE_URL ??
  process.env.NEXT_PUBLIC_SITE_URL ??
  "http://localhost:3000"
).replace(/\/+$/, "");
