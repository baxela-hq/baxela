import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getFormatter, getTranslations } from "next-intl/server";
import { JsonLd } from "@/components/json-ld";
import ProductCard from "@/components/product-card";
import { ProductActions } from "@/components/product-actions";
import { ProductGallery } from "@/components/product-gallery";
import { ProductTabs } from "@/components/product-tabs";
import { StarRating } from "@/components/star-rating";
import { StarSolidIcon } from "@/components/ui/icons";
import SaleBadge from "@/components/sale-badge";
import { fetchCurrency } from "@/lib/api/site";
import { serverApiGet } from "@/lib/api/server";
import { resolveDisplayCurrency } from "@/lib/display-currency";
import type {
  ApiProduct,
  ApiProductComment,
  ApiProductDetail,
  Paginated,
} from "@/lib/api/types";
import { Link } from "@/i18n/navigation";
import { slugsToLocaleUrls } from "@/lib/seo/entity-urls";
import { pageSeo } from "@/lib/seo/metadata";
import {
  breadcrumbLd,
  productGroupLd,
  productLd,
  validAggregateRating,
  webPageLd,
} from "@/lib/seo/schema-org";
import { absoluteAssetUrl, absoluteUrl, type Locale } from "@/lib/seo/urls";

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/products/[id]">): Promise<Metadata> {
  const { id, locale } = await params;
  const product = await serverApiGet<ApiProductDetail>(
    `/catalog/public/products/${id}`,
  ).catch(() => null);

  // Missing products render a real 404 — keep that out of the index.
  if (!product) {
    return { robots: { index: false, follow: false } };
  }

  // The canonical is always the slug form (never the numeric id); the
  // alternate set comes from the product's per-locale slug map, so a set
  // never contains a URL that doesn't resolve to this product.
  const canonicalSlug = product.slug ?? product.id.toString();
  const localeUrls = {
    ...slugsToLocaleUrls("/products", product.slugs),
    [locale as Locale]: absoluteUrl(
      `/products/${encodeURIComponent(canonicalSlug)}`,
      locale as Locale,
    ),
  };

  return pageSeo({
    title: product.title ?? "Product",
    description: product.description,
    locale: locale as Locale,
    localeUrls,
    imageUrl: product.images[0]?.url,
  });
}

export default async function ProductPage({
  params,
}: PageProps<"/[locale]/products/[id]">) {
  const { id, locale } = await params;
  // The param is the product slug (linked everywhere) but numeric ids keep
  // working — the backend resolves both.
  const product = await serverApiGet<ApiProductDetail>(
    `/catalog/public/products/${id}`,
  ).catch(() => null);
  if (!product) {
    notFound();
  }
  const productId = product.id;
  // Display currency for this render (per-request cached) — prices arrive
  // in the base currency and convert for display only
  const display = await resolveDisplayCurrency(locale);
  const productHref = `/products/${product.slug ?? product.id}`;

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

  // Structured data (gap-B2): the indexable offer carries the store's
  // DEFAULT currency — the display conversion below is cosmetic and never
  // reaches the JSON-LD (D5 v2.1). Variable products emit ProductGroup +
  // per-variant offers; per-variant availability isn't exposed yet, so it
  // is omitted there rather than inherited from the product (D4 v2.1).
  const productUrl = absoluteUrl(
    `/products/${encodeURIComponent(product.slug ?? product.id)}`,
    locale as Locale,
  );
  const currencyCode = (await fetchCurrency())?.code ?? "USD";
  const imageUrls = photos
    .map((photo) => absoluteAssetUrl(photo.url))
    .filter((url): url is string => Boolean(url));

  const productGraph: object[] = [];
  if (product.variants.length > 1) {
    productGraph.push(
      productGroupLd({
        url: productUrl,
        name: product.title ?? "Product",
        description: product.description,
        image: imageUrls,
        brandName: product.brand?.title ?? null,
        productGroupId: String(product.id),
        variants: product.variants.map((variant) =>
          productLd({
            url: productUrl,
            name: product.title ?? "Product",
            sku: variant.sku,
            price: variant.price,
            currency: currencyCode,
          }),
        ),
        groupRating: validAggregateRating(product.rating_avg, product.rating_count),
      }),
    );
  } else {
    productGraph.push(
      productLd({
        url: productUrl,
        name: product.title ?? "Product",
        description: product.description,
        image: imageUrls,
        sku: product.variants[0]?.sku ?? null,
        brandName: product.brand?.title ?? null,
        price: product.price,
        currency: currencyCode,
        availability: product.availability ?? null,
        ratingAvg: product.rating_avg,
        ratingCount: product.rating_count,
      }),
    );
  }
  productGraph.push(
    breadcrumbLd(productUrl, [
      { name: tLayout("breadcrumb.links.home"), url: absoluteUrl("/", locale as Locale) },
      { name: tLayout("header.nav.products"), url: absoluteUrl("/products", locale as Locale) },
      { name: product.title ?? "Product", url: productUrl },
    ]),
    webPageLd({
      url: productUrl,
      name: product.title ?? "Product",
      mainEntityId: `${productUrl}#product`,
      breadcrumbId: `${productUrl}#breadcrumb`,
    }),
  );

  return (
    <>
      <JsonLd data={productGraph} />
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
          <ProductGallery photos={photos} title={product.title ?? ""} />

          <div>
            {product.categories[0]?.title || product.brand?.title ? (
              <p className="flex flex-wrap items-center gap-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {product.brand?.slug ? (
                  <Link
                    href={`/brands/${encodeURIComponent(product.brand.slug)}`}
                    className="font-medium text-secondary-text transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
                  >
                    {product.brand.title}
                  </Link>
                ) : null}
                {product.brand?.title && product.categories[0]?.title ? (
                  <span aria-hidden="true">•</span>
                ) : null}
                {product.categories[0]?.title ? (
                  <span>{product.categories[0].title}</span>
                ) : null}
              </p>
            ) : null}
            <h1 className="mt-2 text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
              {product.title}
            </h1>
            <div className="mt-3 flex items-center gap-2">
              {product.rating_count > 0 ? (
                <StarRating rating={product.rating_avg} size="size-4" />
              ) : (
                <StarSolidIcon className="size-5 text-accent" />
              )}
              <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("texts.reviews_count", {
                  count: commentsPage?.meta.total ?? 0,
                })}
              </span>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1">
              <p className="text-2xl font-semibold text-foreground">
                {product.price !== null
                  ? format.number(display.convert(Number(product.price)), "price")
                  : null}
              </p>
              {product.compare_price !== null ? (
                <p className="text-lg text-secondary-text line-through rtl:normal-case rtl:tracking-normal">
                  {format.number(
                    display.convert(Number(product.compare_price)),
                    "price",
                  )}
                </p>
              ) : null}
              <SaleBadge
                price={
                  product.price !== null
                    ? String(display.convert(Number(product.price)))
                    : null
                }
                comparePrice={
                  product.compare_price !== null
                    ? String(display.convert(Number(product.compare_price)))
                    : null
                }
              />
            </div>
            {product.description ? (
              <p className="mt-4 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
                {product.description}
              </p>
            ) : null}

            <ProductActions
              productHref={productHref}
              productId={productId}
              variants={product.variants}
            />
          </div>
        </div>
      </section>

      <ProductTabs
        productId={product.id}
        content={product.content}
        attributes={product.attributes}
        comments={comments}
        commentsTotal={commentsPage?.meta.total ?? 0}
        ratingAvg={product.rating_avg}
        ratingCount={product.rating_count}
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
