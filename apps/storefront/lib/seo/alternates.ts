// hreflang alternate generation (gap-B2, D1 v2): URL construction is the
// CALLER's job — this module only composes the Next.js `alternates` shape
// from a per-locale URL map. Only locales whose URL actually resolves to
// the same entity may appear; alternates are reciprocal by construction
// because every page builds its set from the same map.

import type { Metadata } from "next";
import { absoluteUrl, DEFAULT_LOCALE, LOCALES, type Locale } from "@/lib/seo/urls";

/** Absolute URL per locale; a missing entry means "no such translation". */
export type LocaleUrls = Partial<Record<Locale, string>>;

export function buildAlternates(
  localeUrls: LocaleUrls,
  currentLocale: Locale,
): Metadata["alternates"] {
  const languages: Record<string, string> = {};

  for (const [locale, url] of Object.entries(localeUrls)) {
    if (url) {
      languages[locale] = url;
    }
  }

  // Consistent with the sitemap's convention (lib/sitemap.ts): x-default
  // points at the default locale's variant when it exists.
  const xDefault = localeUrls[DEFAULT_LOCALE];
  if (xDefault) {
    languages["x-default"] = xDefault;
  }

  return {
    ...(localeUrls[currentLocale] ? { canonical: localeUrls[currentLocale] } : {}),
    ...(Object.keys(languages).length > 0 ? { languages } : {}),
  };
}

/**
 * Locale-invariant paths (home, /products, /brands, /blog, /contact, …)
 * resolve in every locale — the full alternate set, always.
 */
export function staticLocaleUrls(path: string): Record<Locale, string> {
  const entries = LOCALES.map((locale) => [locale, absoluteUrl(path, locale)] as const);
  return Object.fromEntries(entries) as Record<Locale, string>;
}
