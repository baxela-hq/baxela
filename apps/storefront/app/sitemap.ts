import type { MetadataRoute } from "next";
import { api } from "@/lib/api/client";
import {
  entityUrls,
  staticUrls,
  type ContentSitemapFeed,
  type SitemapEntry,
} from "@/lib/sitemap";
import { SITE_URL } from "@/lib/site-url";

// Sitemaps must live at the host root — outside app/[locale]. The
// proxy matcher skips dot-paths, so /sitemap.xml never hits the i18n
// redirect. Regenerated hourly: generous next to the backend feeds'
// own cache and keeps crawler load off the API.
export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  // api.get (not serverApiGet): there is no request locale at the root,
  // and the feeds return every language's slug regardless.
  const [products, content] = await Promise.all([
    api.get<SitemapEntry[]>("/catalog/public/sitemap").catch(() => null),
    api.get<ContentSitemapFeed>("/content/public/sitemap").catch(() => null),
  ]);

  return [
    // Static routes (backend down ⇒ the sitemap still lists these)
    ...staticUrls("", { priority: 1.0, changeFrequency: "daily" }),
    ...staticUrls("/products", { priority: 0.9, changeFrequency: "daily" }),
    ...staticUrls("/blog", { priority: 0.7, changeFrequency: "daily" }),
    ...staticUrls("/contact", { priority: 0.5, changeFrequency: "monthly" }),

    // Catalog: /[locale]/products/[slug] (categories and brands have no
    // dedicated routes — they filter /products by query params)
    ...(products ?? []).flatMap((product) =>
      entityUrls(
        product,
        (locale, slug) => `${SITE_URL}/${locale}/products/${slug}`,
        { priority: 0.8, changeFrequency: "daily" },
      ),
    ),

    // Content: /[locale]/blog/[slug], /[locale]/blog/cat/[slug],
    // /[locale]/pages/[slug]
    ...(content?.posts ?? []).flatMap((post) =>
      entityUrls(post, (locale, slug) => `${SITE_URL}/${locale}/blog/${slug}`, {
        priority: 0.6,
        changeFrequency: "weekly",
      }),
    ),
    ...(content?.post_categories ?? []).flatMap((category) =>
      entityUrls(
        category,
        (locale, slug) => `${SITE_URL}/${locale}/blog/cat/${slug}`,
        { priority: 0.4, changeFrequency: "weekly" },
      ),
    ),
    ...(content?.pages ?? []).flatMap((page) =>
      entityUrls(page, (locale, slug) => `${SITE_URL}/${locale}/pages/${slug}`, {
        priority: 0.4,
        changeFrequency: "monthly",
      }),
    ),
  ];
}
