import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site-url";

// Sits at app root (dot-paths bypass the i18n proxy) so crawlers find
// it at /robots.txt and get pointed at the sitemap.
export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        // Utility/account routes carry no indexable content; the
        // patterns also cover the locale-prefixed variants (/*/cart,
        // /fa/profile/…).
        disallow: [
          "/*/cart",
          "/*/checkout",
          "/*/payment",
          "/*/profile",
          "/*/login",
          "/*/signup",
          "/*/forgot-password",
          "/*/enter-otp",
        ],
      },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
