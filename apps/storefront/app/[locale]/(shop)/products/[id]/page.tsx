import type { Metadata } from "next";
import { getFormatter, getTranslations } from "next-intl/server";
import ProductCard, {
  type Product,
} from "@/components/product-card";
import { ProductTabs } from "@/components/product-tabs";
import { Button } from "@/components/ui/button";
import {
  HeartIcon,
  StarSolidIcon,
} from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

export interface ProductDetail {
  id: number;
  name: string;
  price: number;
  rating: number;
  reviewCount: number;
  sizes: string[];
}

const PRODUCT: ProductDetail = {
  id: 1,
  name: "Product 1",
  price: 74.99,
  rating: 4.8,
  reviewCount: 12,
  sizes: ["XS", "S", "M", "L", "XL"],
};

const RELATED: Product[] = [
  { id: 2, name: "Product 2", price: 39.99 },
  { id: 3, name: "Product 3", price: 49.99 },
  { id: 4, name: "Product 4", price: 59.99 },
];

export const metadata: Metadata = {
  title: "Product — Baxela Storefront",
};

export default async function ProductPage() {
  const [t, tLayout, format] = await Promise.all([
    getTranslations("catalog.product"),
    getTranslations("shared.layout"),
    getFormatter(),
  ]);

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
              href="/products"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("header.nav.products")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li className="font-medium text-foreground">{PRODUCT.name}</li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2">
          <div className="space-y-4">
            <div
              className="aspect-square w-full rounded-default border border-border bg-muted"
              role="img"
              aria-label={t("labels.main_image", { name: PRODUCT.name })}
            />
            <div className="grid grid-cols-4 gap-4">
              {[1, 2, 3, 4].map((thumb) => (
                <div
                  key={thumb}
                  className="aspect-square rounded-default border border-border bg-muted"
                />
              ))}
            </div>
          </div>

          <div>
            <p className="text-sm text-secondary-text">Sneakers</p>
            <h1 className="mt-2 text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
              {PRODUCT.name}
            </h1>
            <div className="mt-3 flex items-center gap-2">
              <StarSolidIcon className="size-5 text-accent" />
              <span className="text-sm text-foreground">
                {format.number(PRODUCT.rating)}
              </span>
              <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("texts.reviews_count", { count: PRODUCT.reviewCount })}
              </span>
            </div>
            <p className="mt-4 text-2xl font-semibold text-foreground">
              {format.number(PRODUCT.price, {
                style: "currency",
                currency: "USD",
              })}
            </p>
            <p className="mt-4 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
              Built for everyday wear with materials chosen to keep their
              shape and feel wash after wash. A relaxed fit and understated
              design make this an easy piece to live in.
            </p>

            <div className="mt-6">
              <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
                {t("labels.size")}
              </h2>
              <div className="mt-3 flex flex-wrap gap-2">
                {PRODUCT.sizes.map((size) => (
                  <button
                    key={size}
                    type="button"
                    aria-pressed={size === "M"}
                    className={
                      size === "M"
                        ? "rounded-default border border-primary bg-primary px-4 py-2 text-sm text-primary-foreground"
                        : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                    }
                  >
                    {size}
                  </button>
                ))}
              </div>
            </div>

            <div className="mt-8 flex items-center gap-4">
              <div className="flex items-center rounded-default border border-border">
                <button
                  type="button"
                  aria-label={t("labels.quantity_decrease")}
                  className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
                >
                  −
                </button>
                <span
                  className="px-4 text-sm text-foreground"
                  aria-live="polite"
                >
                  1
                </span>
                <button
                  type="button"
                  aria-label={t("labels.quantity_increase")}
                  className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
                >
                  +
                </button>
              </div>
              <Button type="button" className="flex-1">
                {t("actions.add_to_cart")}
              </Button>
              <button
                type="button"
                aria-label={t("actions.add_to_wishlist")}
                className="rounded-default border border-border p-3.5 text-foreground transition-colors hover:bg-muted"
              >
                <HeartIcon className="size-5" />
              </button>
            </div>
          </div>
        </div>
      </section>

      <ProductTabs />

      <section className="mx-auto max-w-7xl px-6 pb-16">
        <h2 className="text-2xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.related_title")}
        </h2>
        <div className="mt-8 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
          {RELATED.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      </section>
    </>
  );
}
