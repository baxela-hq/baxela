// Locale-aware URL construction for the SEO layer (gap-B2, D1/D8). Pure
// string helpers — no React, no Next — so builders and tests stay
// framework-independent.

import { routing } from "@/i18n/routing";
import { SITE_URL } from "@/lib/site-url";

export type Locale = (typeof routing.locales)[number];

export const DEFAULT_LOCALE = routing.defaultLocale as Locale;

export const LOCALES = routing.locales as readonly Locale[];

export function isLocale(value: string): value is Locale {
  return (LOCALES as readonly string[]).includes(value);
}

/**
 * The absolute URL of `path` in `locale`. `localePrefix: "always"` means
 * every locale lives under its own prefix; the root path stays prefix-only
 * (`/en`, not `/en/`).
 */
export function absoluteUrl(path: string, locale: Locale): string {
  const suffix = path === "/" ? "" : path;
  return `${SITE_URL}/${locale}${suffix}`;
}

/** The storefront origin (absolute, no trailing slash). */
export function siteUrl(): string {
  return SITE_URL;
}

/** Make a media/API URL absolute against the storefront origin. */
export function absoluteAssetUrl(url: string | null | undefined): string | undefined {
  if (!url) {
    return undefined;
  }
  if (/^https?:\/\//.test(url)) {
    return url;
  }
  return `${SITE_URL}${url.startsWith("/") ? "" : "/"}${url}`;
}
