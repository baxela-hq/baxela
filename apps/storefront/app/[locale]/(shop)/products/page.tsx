import { getTranslations } from "next-intl/server";
import ProductCard, {
  type Product,
} from "@/components/product-card";
import ProductFilters from "@/components/product-filters";
import { Link } from "@/i18n/navigation";

const CATEGORY_FILTERS = [
  { label: "Sneakers", count: 123, checked: true },
  { label: "Apparel", count: 89, checked: false },
  { label: "Accessories", count: 56, checked: false },
  { label: "Footwear", count: 45, checked: false },
];

const SIZE_FILTERS = ["XS", "S", "M", "L", "XL"];

const PRODUCTS: Product[] = [
  { id: 1, name: "Product 1", price: 29.99 },
  { id: 2, name: "Product 2", price: 39.99 },
  { id: 3, name: "Product 3", price: 49.99 },
  { id: 4, name: "Product 4", price: 59.99 },
  { id: 5, name: "Product 5", price: 34.99 },
  { id: 6, name: "Product 6", price: 44.99 },
  { id: 7, name: "Product 7", price: 54.99 },
  { id: 8, name: "Product 8", price: 64.99 },
  { id: 9, name: "Product 9", price: 74.99 },
];

export const metadata = {
  title: "Products — Baxela Storefront",
};

export default async function ProductsPage({
  searchParams,
}: PageProps<"/[locale]/products">) {
  const [t, tLayout] = await Promise.all([
    getTranslations("catalog.products"),
    getTranslations("shared.layout"),
  ]);

  const { q } = await searchParams;
  const query = (Array.isArray(q) ? q[0] : q)?.trim() ?? "";
  const normalizedQuery = query.toLowerCase();
  const visibleProducts = query
    ? PRODUCTS.filter((product) =>
        product.name.toLowerCase().includes(normalizedQuery),
      )
    : PRODUCTS;

  const sortOptions = [
    t("sort.options.featured"),
    t("sort.options.newest"),
    t("sort.options.price_low_high"),
    t("sort.options.price_high_low"),
  ];

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
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {query
            ? t("texts.search_results_count", { count: visibleProducts.length })
            : t("texts.results_count", { from: 1, to: 9, total: 123 })}
        </p>

        <ProductFilters
          categories={CATEGORY_FILTERS}
          sizes={SIZE_FILTERS}
          sortOptions={sortOptions}
        >
          {visibleProducts.length > 0 ? (
            <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
              {visibleProducts.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          ) : (
            <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("texts.empty_search", { query })}
            </p>
          )}

          <nav
            aria-label={t("pagination.labels.navigation")}
            className="mt-12 flex flex-wrap items-center justify-center gap-2"
          >
            <button
              type="button"
              className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
            >
              {t("pagination.actions.previous")}
            </button>
            {[1, 2, 3].map((page) => (
              <button
                key={page}
                type="button"
                aria-current={page === 1 ? "page" : undefined}
                className={
                  page === 1
                    ? "rounded-default bg-primary px-4 py-2 text-sm text-primary-foreground"
                    : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                }
              >
                {page.toLocaleString()}
              </button>
            ))}
            <button
              type="button"
              className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
            >
              {t("pagination.actions.next")}
            </button>
          </nav>
        </ProductFilters>
      </section>
    </>
  );
}
