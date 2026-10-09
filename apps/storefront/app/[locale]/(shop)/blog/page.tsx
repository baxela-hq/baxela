import { getTranslations } from "next-intl/server";
import PostCard from "@/components/blog/post-card";
import { serverApiGet } from "@/lib/api/server";
import type { ApiPost, ApiPostCategory, Paginated } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

export const metadata = {
  title: "Blog — Baxela Storefront",
};

export default async function BlogPage({
  searchParams,
}: PageProps<"/[locale]/blog">) {
  const [t, tCommon, tLayout] = await Promise.all([
    getTranslations("blog.posts"),
    getTranslations("shared.common"),
    getTranslations("shared.layout"),
  ]);

  const sp = await searchParams;
  const category = firstParam(sp.category);
  const page = Math.max(1, Number.parseInt(firstParam(sp.page) || "1", 10) || 1);

  const [categoriesPage, postsPage] = await Promise.all([
    serverApiGet<Paginated<ApiPostCategory>>(
      "/content/public/post-categories?per_page=100",
    ).catch(() => null),
    serverApiGet<Paginated<ApiPost>>(
      `/content/public/posts?per_page=9&page=${page}${category ? `&category=${encodeURIComponent(category)}` : ""}`,
    ).catch(() => null),
  ]);

  const categories = categoriesPage?.data ?? [];
  const posts = postsPage?.data ?? [];
  const meta = postsPage?.meta;
  const activeCategory = category
    ? categories.find((c) => c.slug === category)
    : undefined;

  const pageHref = (target: number, targetCategory?: string) => {
    const params = new URLSearchParams();
    if (targetCategory) params.set("category", targetCategory);
    if (target > 1) params.set("page", String(target));
    const qs = params.toString();
    return qs ? `/blog?${qs}` : "/blog";
  };

  const categoryHref = (slug: string | null) =>
    slug ? `/blog?category=${encodeURIComponent(slug)}` : "/blog";

  const lastPage = meta?.last_page ?? 1;
  const pages = Array.from({ length: lastPage }, (_, i) => i + 1);

  return (
    <>
      <nav
        aria-label={tLayout("breadcrumb.labels.navigation")}
        className="border-b border-border-light bg-muted"
      >
        <ol className="mx-auto flex max-w-7xl items-center gap-2 px-6 py-4 text-sm text-secondary-text">
          <li>
            <Link
              href="/"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("breadcrumb.links.home")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.title")}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {activeCategory
            ? t("texts.category_title", { category: activeCategory.title ?? "" })
            : t("texts.title")}
        </h1>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.subtitle")}
        </p>

        {categories.length > 0 ? (
          <nav
            aria-label={t("labels.categories")}
            className="mt-6 flex flex-wrap items-center gap-2"
          >
            <Link
              href={categoryHref(null)}
              className={
                category
                  ? "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                  : "rounded-default bg-primary px-4 py-2 text-sm text-primary-foreground rtl:normal-case rtl:tracking-normal"
              }
            >
              {t("labels.all_categories")}
            </Link>
            {categories.map((c) =>
              c.slug ? (
                <Link
                  key={c.id}
                  href={categoryHref(c.slug)}
                  className={
                    category === c.slug
                      ? "rounded-default bg-primary px-4 py-2 text-sm text-primary-foreground rtl:normal-case rtl:tracking-normal"
                      : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                  }
                >
                  {c.title}
                </Link>
              ) : null,
            )}
          </nav>
        ) : null}

        {postsPage === null ? (
          <p
            role="alert"
            className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {tCommon("messages.error.general")}
          </p>
        ) : posts.length > 0 ? (
          <>
            <p className="mt-8 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("texts.results_count", {
                from: meta?.from ?? 0,
                to: meta?.to ?? 0,
                total: meta?.total ?? 0,
              })}
            </p>
            <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
              {posts.map((post) => (
                <PostCard key={post.id} post={post} />
              ))}
            </div>
          </>
        ) : (
          <p className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
            {activeCategory ? t("texts.empty_category") : t("texts.empty")}
          </p>
        )}

        {lastPage > 1 && postsPage !== null ? (
          <nav
            aria-label={t("pagination.labels.navigation")}
            className="mt-12 flex flex-wrap items-center justify-center gap-2"
          >
            <Link
              href={pageHref(Math.max(1, page - 1), category)}
              aria-disabled={page <= 1}
              className={`rounded-default border border-border px-4 py-2 text-sm transition-colors ${
                page <= 1
                  ? "pointer-events-none opacity-50"
                  : "text-foreground hover:bg-muted"
              }`}
            >
              {t("pagination.actions.previous")}
            </Link>
            {pages.map((p) => (
              <Link
                key={p}
                href={pageHref(p, category)}
                aria-current={p === page ? "page" : undefined}
                className={
                  p === page
                    ? "rounded-default bg-primary px-4 py-2 text-sm text-primary-foreground"
                    : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                }
              >
                {p.toLocaleString()}
              </Link>
            ))}
            <Link
              href={pageHref(Math.min(lastPage, page + 1), category)}
              aria-disabled={page >= lastPage}
              className={`rounded-default border border-border px-4 py-2 text-sm transition-colors ${
                page >= lastPage
                  ? "pointer-events-none opacity-50"
                  : "text-foreground hover:bg-muted"
              }`}
            >
              {t("pagination.actions.next")}
            </Link>
          </nav>
        ) : null}
      </section>
    </>
  );
}
