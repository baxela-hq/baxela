import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const nextConfig: NextConfig = {
  // LAN IP, so phones on the same network can load dev assets from the dev
  // server (Next blocks cross-origin dev resources by default).
  allowedDevOrigins: ["192.168.100.213"],
};

const withNextIntl = createNextIntlPlugin();

export default withNextIntl(nextConfig);
