// schema.org builders (gap-B2, D4/D7/D8): pure functions — no React, no
// Next — transforming trustworthy domain data into JSON-LD nodes. Values
// trace to API/store settings; anything unavailable is OMITTED (never
// invented, never empty): no offers without a real price, no ratings
// without a valid visible aggregate, no availability when unreliable.

import { siteUrl } from "@/lib/seo/urls";

const SCHEMA = "https://schema.org/";

export type JsonLdNode = { "@type": string; "@id"?: string } & Record<string, unknown>;

/** Stable @id fragments — one coherent graph across all pages. */
export function organizationId(): string {
  return `${siteUrl()}/#organization`;
}

export function websiteId(): string {
  return `${siteUrl()}/#website`;
}

export function pageId(url: string): string {
  return `${url}#webpage`;
}

export function productId(url: string): string {
  return `${url}#product`;
}

export function breadcrumbId(url: string): string {
  return `${url}#breadcrumb`;
}

export function organizationLd(input: {
  name: string;
  url: string;
  logo?: string | null;
}): JsonLdNode {
  return {
    "@type": "Organization",
    "@id": organizationId(),
    name: input.name,
    url: input.url,
    ...(input.logo ? { logo: input.logo } : {}),
  };
}

export function websiteLd(input: { name: string; url: string }): JsonLdNode {
  return {
    "@type": "WebSite",
    "@id": websiteId(),
    name: input.name,
    url: input.url,
    publisher: { "@id": organizationId() },
  };
}

export function webPageLd(input: {
  url: string;
  name: string;
  mainEntityId?: string;
  breadcrumbId?: string;
}): JsonLdNode {
  return {
    "@type": "WebPage",
    "@id": pageId(input.url),
    url: input.url,
    name: input.name,
    isPartOf: { "@id": websiteId() },
    ...(input.mainEntityId ? { mainEntity: { "@id": input.mainEntityId } } : {}),
    ...(input.breadcrumbId ? { breadcrumb: { "@id": input.breadcrumbId } } : {}),
  };
}

export function breadcrumbLd(
  url: string,
  items: { name: string; url: string }[],
): JsonLdNode {
  return {
    "@type": "BreadcrumbList",
    "@id": breadcrumbId(url),
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

const AVAILABILITY_URLS: Record<string, string> = {
  in_stock: `${SCHEMA}InStock`,
  out_of_stock: `${SCHEMA}OutOfStock`,
};

/**
 * Google review-snippet rules: only a genuine, visible aggregate — numeric,
 * inside the 1–5 scale, counted. Anything else emits no node at all.
 */
export function validAggregateRating(
  ratingAvg: string | null | undefined,
  ratingCount: number | null | undefined,
): { ratingValue: number; ratingCount: number } | null {
  if (ratingCount === null || ratingCount === undefined || ratingCount <= 0) {
    return null;
  }
  const value = Number(ratingAvg);
  if (!Number.isFinite(value) || value <= 0 || value > 5) {
    return null;
  }
  return { ratingValue: value, ratingCount };
}

export function offerLd(input: {
  url: string;
  /** Active selling price in the store's default currency (D5 v2.1). */
  price: string;
  currency: string;
  availability?: string | null;
}): JsonLdNode {
  const availabilityUrl = input.availability
    ? AVAILABILITY_URLS[input.availability]
    : undefined;

  return {
    "@type": "Offer",
    url: input.url,
    price: input.price,
    priceCurrency: input.currency,
    // Unreliable availability is omitted, never guessed (D6 v2.1).
    ...(availabilityUrl ? { availability: availabilityUrl } : {}),
    itemCondition: `${SCHEMA}NewCondition`,
  };
}

/** One concrete product node — the PDP main entity or a group variant. */
export function productLd(input: {
  url: string;
  name: string;
  description?: string | null;
  image?: string[];
  sku?: string | null;
  brandName?: string | null;
  /** No reliable price → no Offer node (never a fabricated 0). */
  price: string | null;
  currency: string;
  availability?: string | null;
  ratingAvg?: string | null;
  ratingCount?: number | null;
}): JsonLdNode {
  const rating = validAggregateRating(input.ratingAvg, input.ratingCount);
  const hasPrice = input.price !== null && input.price !== "";

  return {
    "@type": "Product",
    "@id": productId(input.url),
    name: input.name,
    ...(input.description ? { description: input.description } : {}),
    ...(input.image && input.image.length > 0 ? { image: input.image } : {}),
    ...(input.sku ? { sku: input.sku } : {}),
    ...(input.brandName ? { brand: { "@type": "Brand", name: input.brandName } } : {}),
    ...(hasPrice
      ? {
          offers: offerLd({
            url: input.url,
            price: input.price as string,
            currency: input.currency,
            availability: input.availability,
          }),
        }
      : {}),
    ...(rating
      ? {
          aggregateRating: {
            "@type": "AggregateRating",
            ratingValue: rating.ratingValue,
            ratingCount: rating.ratingCount,
          },
        }
      : {}),
  };
}

/**
 * Variable products: ProductGroup with nested hasVariant products (D4
 * v2.1). `variesBy` is emitted only when callers can map option axes to
 * real schema.org property URLs — an unmappable axis means omit, never
 * guess. Per-variant offers carry their own price; availability is per
 * variant when known and OMITTED otherwise (never inherited from the
 * group). Group-wide ratings belong on the group, variant ratings on the
 * variant — the caller decides by data, not by default.
 */
export function productGroupLd(input: {
  url: string;
  name: string;
  description?: string | null;
  image?: string[];
  brandName?: string | null;
  productGroupId: string;
  variesBy?: string[];
  variants: JsonLdNode[];
  groupRating?: { ratingValue: number; ratingCount: number } | null;
}): JsonLdNode {
  const rating = input.groupRating ?? null;

  return {
    "@type": "ProductGroup",
    "@id": productId(input.url),
    name: input.name,
    ...(input.description ? { description: input.description } : {}),
    ...(input.image && input.image.length > 0 ? { image: input.image } : {}),
    ...(input.brandName ? { brand: { "@type": "Brand", name: input.brandName } } : {}),
    productGroupID: input.productGroupId,
    ...(input.variesBy && input.variesBy.length > 0
      ? { variesBy: input.variesBy.map((property) => `${SCHEMA}${property}`) }
      : {}),
    hasVariant: input.variants,
    ...(rating
      ? {
          aggregateRating: {
            "@type": "AggregateRating",
            ratingValue: rating.ratingValue,
            ratingCount: rating.ratingCount,
          },
        }
      : {}),
  };
}

export function articleLd(input: {
  url: string;
  name: string;
  description?: string | null;
  image?: string | null;
  datePublished?: string | null;
  dateModified?: string | null;
  authorName?: string | null;
}): JsonLdNode {
  return {
    "@type": "Article",
    "@id": pageId(input.url),
    headline: input.name,
    ...(input.description ? { description: input.description } : {}),
    ...(input.image ? { image: input.image } : {}),
    ...(input.datePublished ? { datePublished: input.datePublished } : {}),
    ...(input.dateModified ? { dateModified: input.dateModified } : {}),
    ...(input.authorName ? { author: { "@type": "Person", name: input.authorName } } : {}),
    publisher: { "@id": organizationId() },
    mainEntityOfPage: { "@id": pageId(input.url) },
  };
}
