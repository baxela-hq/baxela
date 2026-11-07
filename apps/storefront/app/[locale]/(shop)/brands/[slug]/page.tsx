import type { Metadata } from "next";
import NotFoundContent from "@/components/not-found-content";
import BrandProducts from "@/components/brand-products";
import { serverApiGet } from "@/lib/api/server";
import type { ApiBrand } from "@/lib/api/types";
import { canonicalOnlyLocaleUrls } from "@/lib/seo/entity-urls";
import { pageSeo } from "@/lib/seo/metadata";
import type { Locale } from "@/lib/seo/urls";

function fetchBrand(slug: string) {
  return serverApiGet<ApiBrand>(
    `/catalog/public/brands/${encodeURIComponent(slug)}`,
  ).catch(() => null);
}

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/brands/[slug]">): Promise<Metadata> {
  const { slug, locale: localeParam } = await params;
  const locale = localeParam as Locale;
  const brand = await fetchBrand(slug);

  // The missing-brand body renders inline (HTTP 200) — keep it out of
  // the index. Brand slugs are locale-scoped and the payload carries only
  // the current one — self-canonical, no alternates (gap-B2 D1 v2).
  if (!brand) {
    return { robots: { index: false, follow: false } };
  }

  return pageSeo({
    title: brand.title ? `${brand.title} — Baxela Storefront` : "Brands — Baxela Storefront",
    locale,
    localeUrls: canonicalOnlyLocaleUrls("/brands", brand.slug ?? slug, locale),
  });
}

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

export default async function BrandPage({
  params,
  searchParams,
}: PageProps<"/[locale]/brands/[slug]">) {
  const [{ slug }, sp] = await Promise.all([params, searchParams]);
  const page = Math.max(1, Number.parseInt(firstParam(sp.page) || "1", 10) || 1);

  // Unknown brand slugs 404 at the API; notFound() itself renders blank
  // here, so the 404 body is rendered inline instead.
  const brand = await fetchBrand(slug);
  if (!brand || !brand.slug) {
    return <NotFoundContent />;
  }

  return <BrandProducts brand={brand} page={page} />;
}
