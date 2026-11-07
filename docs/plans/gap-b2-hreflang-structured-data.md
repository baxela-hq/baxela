# Plan gap-B2 — Page-level hreflang & Product structured data (JSON-LD)

> **Purpose of this document:** Execution plan for Phase B task **B.2** of
> `docs/PLATFORM_GAPS_ROADMAP.md`. Goal: every indexable storefront page emits
> correct `rel=alternate hreflang` + canonical tags, and the PDP serves `Product`
> JSON-LD rich enough for Google Merchant/Rich-Results eligibility. All facts
> verified 2026-11-07.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** planned,
> awaiting owner review · **Revised same day (v2)** after an external review (8.5/10
> — approve after revisions): five corrections folded in — localized-slug hreflang,
> variant structured-data strategy, crawler-visible price parity (reverses v1's D5),
> purchase-availability semantics, expanded test matrix — see the review trail (§8). ·
> **Size:** ~1–1.5 focused days (three phases).
>
> **Scope note from verification:** the gap roadmap's sitemap item ("per-URL
> hreflang alternates ⚠") turned out to be **already done** — `lib/sitemap.ts`
> builds one `<url>` row per language variant with full alternate sets including
> `x-default`, fed by the backend's per-locale sitemap feeds. B.2 therefore
> narrows to page-level tags + JSON-LD. Conventional commits; never commit
> unless asked.

---

## 1. Verified current state (2026-11-07)

| Fact | State | Evidence |
|---|---|---|
| Sitemap hreflang alternates | ✅ **already implemented** | `apps/storefront/lib/sitemap.ts` (`entityUrls` — per-language rows + `x-default` → default locale), backend per-locale feeds (`ListSitemapAction`) |
| `metadataBase` | ✅ set once in the locale layout | `app/[locale]/layout.tsx:42` (`new URL(SITE_URL)`) |
| Locale URL scheme | ✅ | `i18n/routing.ts`: 6 locales, `defaultLocale: "en"`, `localePrefix: "always"` → every URL is `/{locale}/…`, symmetric |
| **PDP metadata** | ❌ **static hardcoded title** — no per-product title, no description, no canonical, no alternates | `app/[locale]/(shop)/products/[id]/page.tsx:27` (`export const metadata = { title: "Product — …" }`) |
| Other entity pages (brand, blog post, blog category, CMS page) | ⚠ partial | have `generateMetadata` (title, basic robots) but **no canonical/alternates** |
| Products listing + home | ⚠ static metadata only | `products/page.tsx:27`, `(shop)/page.tsx` |
| Auth/profile pages | ❌ no noindex discipline | login/signup/forgot-password/enter-otp/profile |
| **JSON-LD / structured data** | ❌ **absent everywhere** | no `application/ld+json` in app/components/lib |
| Product data available for `Product` JSON-LD | ✅ in the public API payload | `ApiProductDetail`: `title`, `slug`, `description`, `price`, `compare_price`, `images[]`, `brand`, `rating_avg`, `rating_count`, `variants[]` |
| Variant public stock | ❌ not exposed | `ApiVariant` has no stock field — availability needs an additive backend field (v2: purchase-availability enum, D6) |
| **Localized slugs** | ✅ **exist per entity** — the sitemap feeds already carry `{language, slug}` per translated entity | `lib/sitemap.ts` `SitemapTranslation`; backend per-locale feeds. ⚠ The public **show** payload is current-locale-scoped — entity alternates need all locales' slugs (source: existing feed vs additive show field — verify at implementation, prefer no new backend surface) |
| Organization identity source | ⚠ must be store config, not platform branding | Setting module `WEBSITE_TITLE`; Baxela is the *platform* — a merchant's storefront must identify the merchant as seller (review §7) |
| Product SEO translations | ✅ exist backend-side (per-locale meta) | `ProductSeoTranslation` (usable in generateMetadata if the public payload carries it ⚠ verify; product title/description are acceptable fallbacks) |

## 2. Design decisions

| # | Decision | Why |
|---|---|---|
| D1 (v2) | **URL construction is separate from alternate generation.** `buildAlternates(localeUrls: Partial<Record<Locale, string>>, currentLocale)` takes a **per-locale URL map** — never a single path blindly prefixed (localized slugs would silently produce wrong alternates). Static routes get a convenience helper (paths known per locale). Entity pages source each locale's slug from the entity's translation set (exists in the sitemap feeds; ⚠ sourcing via feed vs additive show field — verify, prefer no new backend surface). **Rules:** only emit alternates that resolve to the same entity; alternates are **reciprocal** (each locale references the full set); every locale self-canonicals — **a localized page never canonicals to English**; `x-default` → default locale, consistent with the sitemap. | Six locales × N pages is where copy-paste drifts; wrong alternates are worse than none (review §1). |
| D2 | **PDP gets a real `generateMetadata`** (replacing the static export): title from product title (SEO translation when the payload carries it ⚠), description, canonical + alternates via D1, OG image from the first product image. 404 path keeps `robots: noindex` like the brand page's missing-entity pattern. | The crown-jewel page currently ships a generic title to every crawler. |
| D3 | **Static routes get alternates + canonicals** via D1: home, products listing, brands list/detail, blog list/post/category, CMS pages, contact. **Auth/profile pages get `robots: { index: false }`** — they were never meant for the index. | Completes hreflang coverage; stops index bloat. |
| D4 (v2) | **Two structured-data strategies** (review §2): (a) **simple products** → one `Product` node with its `Offer`; (b) **variable products** → `ProductGroup` with `hasVariant[]` `Product` nodes (per-variant `sku`, `Offer` with the variant's own price), `variesBy` from the option axes, `productGroupID` from the product id — the API's `ApiVariant` (sku, price, compare_price, option_values, is_default) carries what this needs. **Never `AggregateOffer` for variants.** If per-variant availability can't be sourced reliably, offers fall back to the product-level availability and the limitation is documented — the markup must never imply it describes all variants when it doesn't. Builders stay pure functions in `lib/seo/schema-org.ts` + one `<JsonLd data={…}/>` server component emitting a single safely-serialized script per graph. `aggregateRating` only when `rating_count > 0` **and** `rating_avg` is a valid number within the 1–5 scale and the rating is visibly shown on the page (review §5) — invalid/empty data emits no node. | A single Offer under-describes configurable products; Google's ProductGroup model exists exactly for this. |
| D5 (v2) | **JSON-LD price ≡ the crawler-visible price.** The offers carry the **same server-rendered price and currency the page shows** (display currency when one is selected, base otherwise) — never a separately computed value. Use the **active selling price** (promotion applied), with the original price represented via `priceSpecification` when a sale is active; ISO 4217 codes; minor-unit → decimal conversion at one boundary. If a reliable price/currency can't be determined, omit — never fabricate. | v1's D5 (base currency) would have created a visible-price vs structured-data mismatch — exactly what Google penalizes (review §4). |
| D6 (v2) | **Purchase availability, not physical stock.** The public payload gains `availability: string` — an extensible enum (`in_stock`, `out_of_stock`, `backorder`, `preorder` …) derived through the existing `InventoryGateway` from what the customer can actually buy, not raw quantity (Baxela v1 has no backorder/preorder flows, so only the first two values ship — the enum exists so they can land without breaking the contract). Per-variant availability when the variant strategy is active. Storefront maps the enum to schema.org availability URLs. **Unreliable availability is omitted, never guessed.** | A boolean conflates purchasability with stock count (review §3). |
| D7 (v2) | **Placement + coherent entity graph:** `Organization` + `WebSite` in the shop layout with **stable `@id`s** (`{SITE_URL}/#organization`, `/#website`, `{pageUrl}#product` …), cross-referenced (WebSite → Organization; page entities → WebSite) so Google reads one graph rather than unrelated nodes. **Organization identity comes from store settings** (`WEBSITE_TITLE`, public URL) — the merchant, never "Baxela" branding. `Product`+`BreadcrumbList` on the PDP (breadcrumbs mirror the visible navigation), `Article` (with dates/author where available) on blog posts, `BreadcrumbList` on brand/category pages. | Independent unlabeled nodes fragment the store's entity understanding (review §7). |
| D8 (new) | **`lib/seo/` architecture** (review §11): `alternates.ts`, `canonical.ts`, `metadata.ts`, `schema-org.ts`, `urls.ts`, `components/json-ld.tsx`. Builders are **framework-independent pure functions** (no React/Next imports); the `<JsonLd>` component **safely serializes** (escape `<`, `U+2028/2029` — content must not break out of the script tag). Metadata helper composes OG (`og:title/description/url/image`) + Twitter cards + fallbacks — no empty titles, no broken OG images; image URLs absolute and publicly crawlable. | One reusable SEO layer future modules (reviews, sellers, marketplace) extend instead of rewrite. |
| D9 (new) | **The SEO layer transforms domain data; it never invents it.** Every node's values trace to API/store settings; anything unavailable is omitted. Honesty discipline (matching the refund/multi-currency rules): structured data makes pages *eligible* for rich results — it guarantees nothing about Google's rendering; the plan and launch copy never claim otherwise. | Review §11 + repo claim-honesty culture. |

## 3. Work items

### Phase A — Metadata & international SEO (storefront only)
- [ ] `lib/seo/` scaffolding (D8): `urls.ts`, `alternates.ts` (D1 v2 — per-locale URL maps), `canonical.ts`, `metadata.ts` (OG + Twitter cards + fallbacks).
- [ ] PDP `generateMetadata` (D2): title/description (SEO translation ⚠ else product fields), canonical = **slug** form, alternates from the entity's per-locale slugs, OG + Twitter, absolute image URLs.
- [ ] Static routes: home, products, brands (list + detail), blog (list + post + category), pages, contact — canonical + alternates (D3).
- [ ] `robots: { index: false }` on login/signup/forgot-password/enter-otp + profile pages, with correct canonical interaction (noindex pages need no alternates).
- [ ] `pnpm build` + rendered `<head>` spot-check on dev compose (en + fa).

### Phase B — Structured data (storefront + availability backend field)
- [ ] Backend: `availability` enum on the public show payload via `InventoryGateway` (D6 v2) + Pest assertions (in-stock and out-of-stock products).
- [ ] `lib/seo/schema-org.ts` builders + `<JsonLd>` with safe serialization (D4 v2, D8).
- [ ] Entity graph with stable `@id`s (D7 v2): `Organization` + `WebSite` in the shop layout from **store settings**; PDP `Product`/`ProductGroup` + `BreadcrumbList` mirroring the visible breadcrumbs; `Article` (dates/author where available) on posts; `BreadcrumbList` on brand/category.
- [ ] Pricing per D5 v2: active selling price in the rendered currency, `priceSpecification` for the original price on sale, minor-unit conversion at one boundary.
- [ ] Variant strategy (D4 v2): simple → `Product`+`Offer`; variable → `ProductGroup`/`hasVariant`/`variesBy` with per-variant offers (documented fallback to representative-variant if per-variant availability can't be sourced).

### Phase C — Testing & validation (review §9 matrix)
- [ ] Playwright/head assertions: en PDP (canonical + six valid alternates) · fa PDP (fa canonical + **reciprocal** alternates) · localized slug URLs resolve per language · missing product → real **404** · no-price product → no Offer node · no-rating → no `aggregateRating` · rated product → valid `aggregateRating` · variable product → variant representation · out-of-stock → availability · sale → active + original prices · login/profile → noindex · JSON-LD parses with expected `@type`s.
- [ ] Canonical-hygiene rules (D2/§6 of review): `/products/{id}` and outdated slugs → **permanent redirect to the canonical slug** (the backend already resolves both — add the redirect at the storefront or API ⚠ verify where it belongs); query params (tracking/sort/filters/pagination) must not vary the canonical.
- [ ] Validators: Google Rich Results test + schema.org validator on the demo PDP after deploy; Search Console setup line in the launch checklist (human gate; neither validator guarantees display).
- [ ] CHANGELOG + roadmap tick; `docs/PLATFORM_GAPS_ROADMAP.md` B.2 updated (including the sitemap-already-done note).

## 4. Edge cases

- Product without rating, or with invalid/malformed rating data → no `aggregateRating` node (validated: numeric, within 1–5, count positive, visible on page).
- Product without price → omit `offers` rather than emit `0.00`.
- Missing SEO translation → fall back to product title/description; never an empty meta.
- Numeric-id and outdated-slug PDP URLs → permanent redirect to canonical slug; canonical never points at a different product; missing products → real 404.
- Query params (tracking/sort/filters/pagination) → one canonical for equivalent content.
- Only locales whose URL resolves to the same entity appear in an alternate set; no localized page canonicals to English.
- Unreliable availability → omit `availability`; unreliable price → omit `offers`.
- `x-default` → default locale (en), matching the sitemap's existing convention.
- RTL locales need no special JSON-LD handling (schema.org values are locale-neutral strings).

## 5. Out of scope

- Review/rating snippet beyond `aggregateRating` (individual `Review` nodes), FAQ, LocalBusiness — later, demand-driven.
- Sitemap work (already implemented — verified above).
- Admin/backend SEO tooling beyond the existing per-locale translations.
- Any claim that JSON-LD guarantees rich results or Merchant listings (D9) — pages become *eligible*; Google decides display.

## 6. Suggested commits

1. `feat(storefront): hreflang alternates and canonicals with localized slugs`
2. `feat(backend/catalog): expose purchase availability on public products`
3. `feat(storefront): product and organization structured data with entity graph`
4. `test(storefront): assert seo metadata, hreflang reciprocity and json-ld in e2e`

## 8. Review trail

- v1 (2026-11-07) → external review same day (8.5/10, approve after revisions). Five
  corrections folded in: **D1** rewritten (per-locale URL maps, localized slugs,
  reciprocal/self-canonical rules); **D4** split (simple vs variable products,
  `ProductGroup`/`hasVariant`, never `AggregateOffer` for variants, documented
  fallback); **D5** reversed (crawler-visible price parity replaces base-currency
  offers — the v1 rule would have mismatched the visible price); **D6** refined
  (purchase-availability enum via `InventoryGateway`, omit-when-unreliable);
  **Phase C** expanded to the review's 12-case matrix + three validators. Also
  adopted: stable `@id` entity graph (D7), Organization from store settings,
  `lib/seo/` architecture + safe JSON-LD serialization (D8), SEO-layer-transforms-
  never-invents discipline (D9), canonical redirect/query-param rules.

## 7. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase B (B.2) — original gap; its sitemap ⚠ item
  is resolved by the verification note above.
- `apps/storefront/lib/sitemap.ts` — the existing hreflang convention (`x-default`
  → default locale) that D1 mirrors for page tags.
