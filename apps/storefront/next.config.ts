import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // LAN IP, so phones on the same network can load dev assets from the dev
  // server (Next blocks cross-origin dev resources by default).
  allowedDevOrigins: ["192.168.100.213"],
};

export default nextConfig;
