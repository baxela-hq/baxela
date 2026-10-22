import { defineRouting } from "next-intl/routing";

export const routing = defineRouting({
  // All supported locales — keep in sync with messages/{locale}/
  locales: ["en", "fa", "de", "fr", "es", "ar"],

  // English is the primary experience: / redirects to /en; fa/ar render RTL
  defaultLocale: "en",

  // Both locales get a path prefix (/en/…, /fa/…) for symmetric, cache-safe URLs
  localePrefix: "always",
});
