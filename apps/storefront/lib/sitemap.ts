// Sitemap composition helpers: turn the backend's per-module sitemap
// feeds (per-language slugs + updated_at) into Next.js MetadataRoute
// entries with hreflang alternates.

import type { MetadataRoute } from "next";
import { routing } from "@/i18n/routing";
import { SITE_URL } from "@/lib/site-url";

/** One translated slug of an indexable entity, keyed by language code. */
export interface SitemapTranslation {
  language: string;
  slug: string;
}

/** Feed entry shape returned by the backend sitemap endpoints. */
export interface SitemapEntry {
  id: number;
  updated_at: string | null;
  translations: SitemapTranslation[];
}

/** Grouped payload of GET /content/public/sitemap. */
export interface ContentSitemapFeed {
  posts: SitemapEntry[];
  post_categories: SitemapEntry[];
  pages: SitemapEntry[];
}

type UrlOptions = Pick<
  MetadataRoute.Sitemap[number],
  "changeFrequency" | "priority"
>;

// Google's sitemap hreflang spec wants one <url> row per language
// variant, each listing the full alternate set — including x-default
// pointing at the default locale when it is translated.
export function entityUrls(
  entry: SitemapEntry,
  build: (locale: string, slug: string) => string,
  options: UrlOptions = {},
): MetadataRoute.Sitemap {
  const byLocale = new Map<string, string>();

  // Only locales the storefront actually serves — the backend may know
  // more languages than the storefront has routes for.
  for (const translation of entry.translations) {
    if (routing.locales.includes(translation.language as (typeof routing.locales)[number])) {
      byLocale.set(translation.language, build(translation.language, translation.slug));
    }
  }

  if (byLocale.size === 0) {
    return [];
  }

  const languages: Record<string, string> = Object.fromEntries(byLocale);
  if (byLocale.has(routing.defaultLocale)) {
    languages["x-default"] = byLocale.get(routing.defaultLocale)!;
  }

  const lastModified = entry.updated_at ? new Date(entry.updated_at) : undefined;

  return [...byLocale.values()].map((url) => ({
    url,
    ...(lastModified ? { lastModified } : {}),
    ...options,
    alternates: { languages },
  }));
}

// Static routes exist in every served locale; same alternate semantics
// as entityUrls but without lastmod (they change with deploys, not data).
export function staticUrls(
  path: string,
  options: UrlOptions,
): MetadataRoute.Sitemap {
  const byLocale = new Map<string, string>(
    routing.locales.map((locale) => [
      locale,
      path === "" ? `${SITE_URL}/${locale}` : `${SITE_URL}/${locale}${path}`,
    ]),
  );

  const languages: Record<string, string> = Object.fromEntries(byLocale);
  languages["x-default"] = byLocale.get(routing.defaultLocale)!;

  return [...byLocale.values()].map((url) => ({
    url,
    ...options,
    alternates: { languages },
  }));
}
