import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
  // Demo branch deploys to Cloudflare Workers via @opennextjs/cloudflare
  // (see wrangler.jsonc) — no standalone/Docker output here; build the
  // production image from main. Images skip Next's optimizer because the
  // demo has no Cloudflare Images binding.
  images: { unoptimized: true },

  // Any host on the LAN subnet, so phones/other machines can load dev assets
  // from the dev server (Next blocks cross-origin dev resources by default).
  // Wildcards match one dot-segment each, so this survives DHCP IP changes.
  allowedDevOrigins: ["192.168.*.*"],
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
