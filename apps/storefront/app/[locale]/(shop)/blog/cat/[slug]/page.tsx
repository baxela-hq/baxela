import type { Metadata } from "next";
import NotFoundContent from "@/components/not-found-content";
import BlogListing from "@/components/blog/blog-listing";
import { serverApiGet } from "@/lib/api/server";
import type { ApiPostCategory } from "@/lib/api/types";

function fetchCategory(slug: string) {
  return serverApiGet<ApiPostCategory>(
    `/content/public/post-categories/${encodeURIComponent(slug)}`,
  ).catch(() => null);
}

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/blog/cat/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const category = await fetchCategory(slug);
  return {
    title: category?.title ? `${category.title} — Blog` : "Blog — Baxela Storefront",
    // The missing-category body renders inline (HTTP 200) — keep it out of
    // the index.
    ...(category ? {} : { robots: { index: false } }),
  };
}

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

export default async function BlogCategoryPage({
  params,
  searchParams,
}: PageProps<"/[locale]/blog/cat/[slug]">) {
  const [{ slug }, sp] = await Promise.all([params, searchParams]);
  const page = Math.max(1, Number.parseInt(firstParam(sp.page) || "1", 10) || 1);

  // Unknown/hidden category slugs 404 at the API; notFound() itself renders
  // blank here, so the 404 body is rendered inline instead.
  const category = await fetchCategory(slug);
  if (!category || !category.slug) {
    return <NotFoundContent />;
  }

  return <BlogListing category={category} page={page} />;
}
