import { getTranslations } from "next-intl/server";
import { serverApiGet } from "@/lib/api/server";
import type { ApiBrand, Paginated } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

export const metadata = {
  title: "Brands — Baxela Storefront",
};

export default async function BrandsPage() {
  const [t, tCommon, tLayout] = await Promise.all([
    getTranslations("catalog.brands"),
    getTranslations("shared.common"),
    getTranslations("shared.layout"),
  ]);

  const brandsPage = await serverApiGet<Paginated<ApiBrand>>(
    "/catalog/public/brands?per_page=100",
  ).catch(() => null);
  const brands = brandsPage?.data ?? [];

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
          {t("texts.title")}
        </h1>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.subtitle")}
        </p>

        {brandsPage === null ? (
          <p
            role="alert"
            className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {tCommon("messages.error.general")}
          </p>
        ) : brands.length > 0 ? (
          <ul className="mt-8 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
            {brands.map((brand) => (
              <li key={brand.id}>
                <Link
                  href={`/brands/${encodeURIComponent(brand.slug ?? brand.id)}`}
                  className="group flex h-full flex-col items-center gap-4 rounded-default border border-border p-6 text-center transition-colors hover:border-accent hover:bg-muted"
                >
                  {brand.image_url ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={brand.image_url}
                      alt={brand.title ?? ""}
                      className="h-16 w-auto max-w-full object-contain"
                    />
                  ) : (
                    <span
                      aria-hidden="true"
                      className="flex h-16 w-16 items-center justify-center rounded-full bg-muted text-xl font-semibold text-secondary-text"
                    >
                      {brand.title?.charAt(0).toUpperCase() ?? "?"}
                    </span>
                  )}
                  <span className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                    {brand.title}
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-8 text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.empty")}
          </p>
        )}
      </section>
    </>
  );
}
