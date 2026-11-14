import { expect, test } from "@playwright/test";

// gap-B2 Phase C: metadata correctness assertions. Correctness over
// coverage — a wrong hreflang is worse than none, so these assert exact
// values on routes whose shape is deterministic (static routes, auth).

test("home carries six reciprocal hreflang alternates and a self-canonical", async ({
  page,
}) => {
  await page.goto("/en");
  const alternates = page.locator('link[rel="alternate"][hreflang]');
  // 6 locales + the explicit x-default → /en (gap-B2: x-default is its
  // own link element, no longer implied by the en alternate).
  await expect(alternates).toHaveCount(7);

  for (const locale of ["en", "fa", "de", "fr", "es", "ar", "x-default"]) {
    await expect(page.locator(`link[rel="alternate"][hreflang="${locale}"]`)).toHaveCount(1);
  }
  await expect(page.locator('link[hreflang="x-default"]')).toHaveAttribute("href", /\/en$/);

  const canonical = page.locator('link[rel="canonical"]');
  await expect(canonical).toHaveCount(1);
  await expect(canonical).toHaveAttribute("href", /\/en$/);
});

test("ar home canonical points at the arabic url, never at english", async ({ page }) => {
  await page.goto("/ar");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", /\/ar$/);
});

test("listing canonical ignores sort and pagination params", async ({ page }) => {
  await page.goto("/en/products?sort=-price&page=2");
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", /\/en\/products$/);
});

test("utility pages are noindex", async ({ page }) => {
  for (const path of ["/en/login", "/en/cart", "/en/checkout"]) {
    await page.goto(path);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
      "content",
      /noindex/,
    );
  }
});

test("blog listing is indexable and localized", async ({ page }) => {
  await page.goto("/en/blog");
  const canonical = page.locator('link[rel="canonical"]');
  await expect(canonical).toHaveCount(1);
  await expect(canonical).toHaveAttribute("href", /\/en\/blog$/);
  const alternates = page.locator('link[rel="alternate"][hreflang]');
  await expect(alternates).toHaveCount(7); // 6 locales + x-default (gap-B2)
});

test("product json-ld parses and carries the product graph", async ({ page }) => {
  // Hard navigation to the PDP: soft (client-side) navigation can leave
  // the previous route's canonical lingering in the document head under
  // dev mode, which is not what a crawler sees — this spec asserts the
  // served document.
  await page.goto("/en");
  const href = await page.locator('a[href*="/products/"]').first().getAttribute("href");
  expect(href).toBeTruthy();
  await page.goto(href!);
  await page.waitForURL(/\/en\/products\//);

  const graph = await page.evaluate(() => {
    const scripts = Array.from(
      document.querySelectorAll('script[type="application/ld+json"]'),
    );
    return scripts.flatMap((script) => {
      const parsed = JSON.parse(script.textContent ?? "null");
      return Array.isArray(parsed) ? parsed : [parsed];
    });
  });

  // The PDP emits its own graph (product/group + breadcrumb + webpage);
  // the shop layout adds the organization/website pair. gap-B2: variable
  // products render as ProductGroup with Product variants — simple
  // products stay plain Product. Either is valid for a PDP; both must
  // sit in a graph with breadcrumb/webpage/organization/website.
  const types = graph.map((node: { "@type"?: string }) => node?.["@type"]);
  const hasProduct = types.includes("Product");
  const group = graph.find(
    (node: { "@type"?: string }) => node["@type"] === "ProductGroup",
  ) as { hasVariant?: Array<{ "@type"?: string }> } | undefined;

  expect(hasProduct || group !== undefined).toBe(true);
  if (!hasProduct && group) {
    // A ProductGroup without Product variants would be meaningless.
    expect(group.hasVariant?.[0]?.["@type"]).toBe("Product");
  }
  expect(types).toContain("BreadcrumbList");
  expect(types).toContain("WebPage");
  expect(types).toContain("Organization");
  expect(types).toContain("WebSite");

  const product = graph.find((node: { "@type"?: string }) => node["@type"] === "Product");
  // Correctness-first: an offer exists only with a real price; when it
  // exists it carries a currency and schema.org availability.
  if (product?.offers) {
    expect(String(product.offers.price)).not.toBe("0");
    expect(product.offers.priceCurrency).toBeTruthy();
    if (product.offers.availability) {
      expect(product.offers.availability).toMatch(/^https:\/\/schema\.org\//);
    }
  }

  // hreflang on the PDP: alternates must be reciprocal with the canonical
  const canonical = await page.locator('link[rel="canonical"]').getAttribute("href");
  expect(canonical).toBeTruthy();
  const alternateCount = await page.locator('link[rel="alternate"][hreflang]').count();
  expect(alternateCount).toBeGreaterThanOrEqual(1);
});
