import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getFormatter, getTranslations } from "next-intl/server";
import ProductCard from "@/components/product-card";
import { ProductActions } from "@/components/product-actions";
import { ProductTabs } from "@/components/product-tabs";
import { StarSolidIcon } from "@/components/ui/icons";
import { serverApiGet } from "@/lib/api/server";
import type {
  ApiProduct,
  ApiProductComment,
  ApiProductDetail,
  Paginated,
} from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

export const metadata: Metadata = {
  title: "Product — Baxela Storefront",
};

export default async function ProductPage({
  params,
}: PageProps<"/[locale]/products/[id]">) {
  const { id } = await params;
  const productId = Number.parseInt(id, 10);
  if (Number.isNaN(productId)) {
    notFound();
  }

  const product = await serverApiGet<ApiProductDetail>(
    `/catalog/public/products/${productId}`,
  ).catch(() => null);
  if (!product) {
    notFound();
  }

  const [t, tLayout, format, commentsPage, relatedPage] = await Promise.all([
    getTranslations("catalog.product"),
    getTranslations("shared.layout"),
    getFormatter(),
    serverApiGet<Paginated<ApiProductComment>>(
      `/catalog/public/products/${productId}/comments?per_page=10`,
    ).catch(() => null),
    serverApiGet<Paginated<ApiProduct>>(
      "/catalog/public/products?per_page=8",
    ).catch(() => null),
  ]);

  const comments = commentsPage?.data ?? [];
  const related = (relatedPage?.data ?? [])
    .filter((candidate) => candidate.id !== productId)
    .slice(0, 3);

  const photos = product.images.filter((image) => image.collection === "photos");
  const mainImage = photos[0] ?? null;

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
          <li className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {product.title}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2">
          <div className="space-y-4">
            <div
              className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-default border border-border bg-muted"
              role="img"
              aria-label={t("labels.main_image", { name: product.title ?? "" })}
            >
              {mainImage ? (
                // Backend-served images — see product-card for the img rationale
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={mainImage.url}
                  alt={product.title ?? ""}
                  className="size-full object-cover"
                />
              ) : null}
            </div>
            {photos.length > 1 ? (
              <div className="grid grid-cols-4 gap-4">
                {photos.slice(1, 5).map((thumb) => (
                  <div
                    key={thumb.id}
                    className="aspect-square overflow-hidden rounded-default border border-border bg-muted"
                  >
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={thumb.url}
                      alt=""
                      className="size-full object-cover"
                      loading="lazy"
                    />
                  </div>
                ))}
              </div>
            ) : null}
          </div>

          <div>
            {product.categories[0]?.title ? (
              <p className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {product.categories[0].title}
              </p>
            ) : null}
            <h1 className="mt-2 text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
              {product.title}
            </h1>
            <div className="mt-3 flex items-center gap-2">
              <StarSolidIcon className="size-5 text-accent" />
              <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("texts.reviews_count", {
                  count: commentsPage?.meta.total ?? 0,
                })}
              </span>
            </div>
            <p className="mt-4 text-2xl font-semibold text-foreground">
              {product.price !== null
                ? format.number(Number(product.price), {
                    style: "currency",
                    currency: "USD",
                  })
                : null}
            </p>
            {product.description ? (
              <p className="mt-4 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
                {product.description}
              </p>
            ) : null}

            <ProductActions productId={product.id} variants={product.variants} />
          </div>
        </div>
      </section>

      <ProductTabs
        productId={product.id}
        content={product.content}
        comments={comments}
        commentsTotal={commentsPage?.meta.total ?? 0}
      />

      {related.length > 0 ? (
        <section className="mx-auto max-w-7xl px-6 pb-16">
          <h2 className="text-2xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.related_title")}
          </h2>
          <div className="mt-8 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
            {related.map((candidate) => (
              <ProductCard key={candidate.id} product={candidate} />
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
