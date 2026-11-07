import type { Metadata } from "next";
import BlogListing from "@/components/blog/blog-listing";
import { pageSeo } from "@/lib/seo/metadata";
import type { Locale } from "@/lib/seo/urls";

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/blog">): Promise<Metadata> {
  const { locale } = await params;

  return pageSeo({
    title: "Blog — Baxela Storefront",
    locale: locale as Locale,
    path: "/blog",
  });
}

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

export default async function BlogPage({
  searchParams,
}: PageProps<"/[locale]/blog">) {
  const sp = await searchParams;
  const page = Math.max(1, Number.parseInt(firstParam(sp.page) || "1", 10) || 1);

  return <BlogListing page={page} />;
}
