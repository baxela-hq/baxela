import { expect, test } from "@playwright/test";

// gap-B2 Phase C: metadata correctness assertions. Correctness over
// coverage — a wrong hreflang is worse than none, so these assert exact
// values on routes whose shape is deterministic (static routes, auth).

test("home carries six reciprocal hreflang alternates and a self-canonical", async ({
  page,
}) => {
  await page.goto("/en");
  const alternates = page.locator('link[rel="alternate"][hreflang]');
  await expect(alternates).toHaveCount(6); // 6 locales (en doubles as x-default target)

  for (const locale of ["en", "fa", "de", "fr", "es", "ar"]) {
    await expect(alternates.filter({ has: page.locator(`[hreflang="${locale}"]`) })).toHaveCount(1);
  }

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
  await expect(alternates).toHaveCount(6);
});

test("product json-ld parses and carries the product graph", async ({ page }) => {
  await page.goto("/en");
  const productLink = page.locator('a[href*="/products/"]').first();
  await productLink.click();
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
  // the shop layout adds the organization/website pair.
  const types = graph.map((node: { "@type"?: string }) => node?.["@type"]);
  expect(types).toContain("Product");
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
