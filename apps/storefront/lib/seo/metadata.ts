// Metadata composition (gap-B2, D8): one helper every page composes —
// title/description, canonical + hreflang alternates, Open Graph, Twitter
// cards, robots. Missing data is omitted (never empty tags); the SEO layer
// transforms domain data and invents nothing.

import type { Metadata } from "next";
import { buildAlternates, staticLocaleUrls, type LocaleUrls } from "@/lib/seo/alternates";
import { absoluteAssetUrl, type Locale } from "@/lib/seo/urls";

export interface PageSeoInput {
  title: string;
  description?: string | null;
  locale: Locale;
  /** Locale-invariant path (e.g. "/products") — full alternates set. */
  path?: string;
  /** Explicit per-locale URLs; wins over `path` when provided. */
  localeUrls?: LocaleUrls;
  /** First image for OG/Twitter — absolutized if relative. */
  imageUrl?: string | null;
  noindex?: boolean;
  /** OG type; "website" everywhere except articles. */
  ogType?: "website" | "article";
}

export function pageSeo(input: PageSeoInput): Metadata {
  const localeUrls = input.localeUrls ?? staticLocaleUrls(input.path ?? "/");
  const canonical = localeUrls[input.locale];
  const image = absoluteAssetUrl(input.imageUrl);

  const description = input.description?.trim() || undefined;

  return {
    title: input.title,
    ...(description ? { description } : {}),
    alternates: buildAlternates(localeUrls, input.locale),
    ...(input.noindex ? { robots: { index: false, follow: false } } : {}),
    openGraph: {
      title: input.title,
      ...(description ? { description } : {}),
      ...(canonical ? { url: canonical } : {}),
      type: input.ogType ?? "website",
      ...(image ? { images: [{ url: image }] } : {}),
    },
    twitter: {
      card: image ? "summary_large_image" : "summary",
      title: input.title,
      ...(description ? { description } : {}),
      ...(image ? { images: [image] } : {}),
    },
  };
}

/** Metadata for pages crawlers must never index. */
export function noindexMetadata(): Metadata {
  return { robots: { index: false, follow: false } };
}
