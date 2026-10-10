import BlogListing from "@/components/blog/blog-listing";

export const metadata = {
  title: "Blog — Baxela Storefront",
};

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
