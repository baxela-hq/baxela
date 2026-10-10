import { getTranslations } from "next-intl/server";
import ProductCard from "@/components/product-card";
import { serverApiGet } from "@/lib/api/server";
import type { ApiBrand, ApiProduct, Paginated } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

interface BrandProductsProps {
  // Active brand (already validated by the brand page).
  brand: ApiBrand;
  page?: number;
}

/**
 * Server component shared by the brand landing page: products of one brand
 * as a paginated grid, breadcrumb included.
 */
export default async function BrandProducts({
  brand,
  page = 1,
}: BrandProductsProps) {
  const [t, tCommon, tLayout] = await Promise.all([
    getTranslations("catalog.brands"),
    getTranslations("shared.common"),
    getTranslations("shared.layout"),
  ]);

  const productsPage = await serverApiGet<Paginated<ApiProduct>>(
    `/catalog/public/products?per_page=9&page=${page}&filter[brand.id]=${brand.id}`,
  ).catch(() => null);

  const products = productsPage?.data ?? [];
  const meta = productsPage?.meta;

  const basePath = `/brands/${brand.slug}`;
  const pageHref = (target: number) =>
    target > 1 ? `${basePath}?page=${target}` : basePath;

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
          <li>
            <Link
              href="/brands"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {t("texts.title")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li
            aria-current="page"
            className="truncate font-medium text-foreground rtl:normal-case rtl:tracking-normal"
          >
            {brand.title}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.products_by", { brand: brand.title ?? "" })}
        </h1>
        {brand.description ? (
          <p className="mt-2 max-w-2xl text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {brand.description}
          </p>
        ) : null}

        {productsPage === null ? (
          <p
            role="alert"
            className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {tCommon("messages.error.general")}
          </p>
        ) : products.length > 0 ? (
          <>
            <p className="mt-8 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("texts.results_count", {
                from: meta?.from ?? 0,
                to: meta?.to ?? 0,
                total: meta?.total ?? 0,
              })}
            </p>
            <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
              {products.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </>
        ) : (
          <p className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.empty")}
          </p>
        )}

        {lastPage > 1 && productsPage !== null ? (
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
      </section>
    </>
  );
}
