import { defineCloudflareConfig } from "@opennextjs/cloudflare";

// Demo deployment: no R2 bucket, so no incrementalCache override — ISR pages
// simply re-render instead of being served from cache.
export default defineCloudflareConfig();
