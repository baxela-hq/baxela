// Entity-specific locale URL maps (gap-B2, D1 v2): entity translations
// carry per-locale slugs — alternates are built ONLY from slugs that
// exist, so a set never contains a URL that doesn't resolve to the entity.

import type { LocaleUrls } from "@/lib/seo/alternates";
import { absoluteUrl, LOCALES, type Locale } from "@/lib/seo/urls";

/**
 * `slugs` maps locale → slug (the API's per-locale translation map).
 * Locales without a slug get no URL — the alternate set stays honest.
 */
export function slugsToLocaleUrls(
  basePath: string,
  slugs: Record<string, string> | undefined | null,
): LocaleUrls {
  const urls: LocaleUrls = {};
  if (!slugs) {
    return urls;
  }
  for (const locale of LOCALES) {
    const slug = slugs[locale];
    if (slug) {
      urls[locale] = absoluteUrl(`${basePath}/${encodeURIComponent(slug)}`, locale);
    }
  }
  return urls;
}

/**
 * Canonical-only map for detail pages whose per-locale slugs are not
 * exposed (brands, posts, CMS pages — v1 limitation, documented): one
 * entry, the current locale, so the page self-canonicals without
 * asserting alternates that may not resolve.
 */
export function canonicalOnlyLocaleUrls(
  basePath: string,
  slug: string | null | undefined,
  locale: Locale,
  fallback?: string | number,
): LocaleUrls {
  const segment = slug ? encodeURIComponent(slug) : String(fallback ?? "");
  if (!segment) {
    return {};
  }
  return { [locale]: absoluteUrl(`${basePath}/${segment}`, locale) };
}
