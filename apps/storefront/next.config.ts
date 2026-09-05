import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
  // Trace the server into .next/standalone for the production Docker image
  // (infrastructure/docker/production/storefront/Dockerfile runs server.js).
  output: "standalone",

  // Any host on the LAN subnet, so phones/other machines can load dev assets
  // from the dev server (Next blocks cross-origin dev resources by default).
  // Wildcards match one dot-segment each, so this survives DHCP IP changes.
  allowedDevOrigins: ["192.168.*.*"],
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
