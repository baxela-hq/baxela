import { getTranslations } from "next-intl/server";
import ProductCard from "@/components/product-card";
import ProductFilters from "@/components/product-filters";
import { serverApiGet } from "@/lib/api/server";
import type { ApiProduct, ApiPublicCategory, Paginated } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

// URL sort value -> backend allowed sort
const SORT_MAP: Record<string, string> = {
  featured: "-id",
  newest: "created_at",
  price_asc: "price",
  price_desc: "-price",
};

function firstParam(value: string | string[] | undefined): string {
  return (Array.isArray(value) ? value[0] : value)?.trim() ?? "";
}

export const metadata = {
  title: "Products — Baxela Storefront",
};

export default async function ProductsPage({
  searchParams,
}: PageProps<"/[locale]/products">) {
  const [t, tCommon, tLayout] = await Promise.all([
    getTranslations("catalog.products"),
    getTranslations("shared.common"),
    getTranslations("shared.layout"),
  ]);

  const sp = await searchParams;
  const query = firstParam(sp.q);
  const sort = firstParam(sp.sort);
  const category = firstParam(sp.category);
  const page = Math.max(1, Number.parseInt(firstParam(sp.page) || "1", 10) || 1);

  // Categories first: the category param accepts an id or a slug (the
  // mega-menu links use slugs) and must be resolved to an id for the
  // backend's categories.id filter. per_page=100 covers the whole list for
  // the sidebar and the slug lookup.
  const categoriesPage = await serverApiGet<Paginated<ApiPublicCategory>>(
    "/catalog/public/categories?per_page=100",
  ).catch(() => null);
  const categories = categoriesPage?.data ?? [];

  const matchedCategory = category
    ? categories.find(
        (c) => String(c.id) === category || c.slug === category,
      )
    : undefined;
  const categoryId =
    matchedCategory?.id ??
    (/^\d+$/.test(category) ? Number(category) : null);

  const search = new URLSearchParams();
  search.set("per_page", "9");
  if (page > 1) search.set("page", String(page));
  if (query) search.set("filter[title]", query);
  if (categoryId !== null) {
    search.set("filter[categories.id]", String(categoryId));
  }
  if (sort && SORT_MAP[sort]) search.set("sort", SORT_MAP[sort]);

  const productsPage = await serverApiGet<Paginated<ApiProduct>>(
    `/catalog/public/products?${search.toString()}`,
  ).catch(() => null);

  const products = productsPage?.data ?? [];
  const meta = productsPage?.meta;
  const categoryOptions = categories.map((c) => ({
    id: c.id,
    title: c.title,
  }));

  const pageHref = (target: number): string => {
    const params = new URLSearchParams();
    if (query) params.set("q", query);
    if (sort) params.set("sort", sort);
    if (category) params.set("category", category);
    if (target > 1) params.set("page", String(target));
    const qs = params.toString();
    return qs ? `/products?${qs}` : "/products";
  };

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
            {tLayout("header.nav.products")}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {query
            ? t("texts.search_results_title", { query })
            : t("texts.title")}
        </h1>
        {productsPage ? (
          <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {query
              ? t("texts.search_results_count", { count: meta?.total ?? 0 })
              : t("texts.results_count", {
                  from: meta?.from ?? 0,
                  to: meta?.to ?? 0,
                  total: meta?.total ?? 0,
                })}
          </p>
        ) : null}

        {productsPage === null ? (
          <p
            role="alert"
            className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {tCommon("messages.error.general")}
          </p>
        ) : (
          <ProductFilters
            categories={categoryOptions}
            selectedCategoryId={categoryId}
            sortValue={sort || "featured"}
          >
            {products.length > 0 ? (
              <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                {products.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            ) : (
              <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
                {query
                  ? t("texts.empty_search", { query })
                  : t("texts.empty")}
              </p>
            )}

            {lastPage > 1 ? (
              <nav
                aria-label={t("pagination.labels.navigation")}
                className="mt-12 flex flex-wrap items-center justify-center gap-2"
              >
                <Link
                  href={pageHref(Math.max(1, page - 1))}
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
                    href={pageHref(p)}
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
                  href={pageHref(Math.min(lastPage, page + 1))}
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
          </ProductFilters>
        )}
      </section>
    </>
  );
}
