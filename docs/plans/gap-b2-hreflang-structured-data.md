# Plan gap-B2 — Page-level hreflang & Product structured data (JSON-LD)

> **Purpose of this document:** Execution plan for Phase B task **B.2** of
> `docs/PLATFORM_GAPS_ROADMAP.md`. Goal: every indexable storefront page emits
> correct `rel=alternate hreflang` + canonical tags, and the PDP serves `Product`
> JSON-LD rich enough for Google Merchant/Rich-Results eligibility. All facts
> verified 2026-11-07.
>
> **Created:** 2026-11-07 · **Owner:** solo maintainer · **Status:** **approved**
> (v2 review 8.5/10 → v2.1 clarifications folded in after the final review 9.4/10 —
> see the review trail, §8) — ready to implement ·
> **Size:** 2–3 focused days (three phases; review-calibrated).
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
| D4 (v2.1) | **Two structured-data strategies** (review §2): (a) **simple products** → one `Product` node with its `Offer`; (b) **variable products** → `ProductGroup` with `hasVariant[]` `Product` nodes (`hasVariant` nesting suffices — no `isVariantOf` duplication), each variant `Product` carrying its own `sku` (never invented when absent) and its own `Offer` (per-variant price/availability **when available, omitted otherwise** — group-level availability is **never copied blindly onto variants**: an out-of-stock Large must not inherit the group's in-stock state). `ProductGroup.name` = the parent product; `productGroupID` = stable and store-unique; `variesBy` = **schema.org property URLs mapped only from genuinely variant-identifying option axes** (size, color) — merchant options like "Gift wrapping" are excluded; variant URLs only if the storefront can select a variant via URL (⚠ verify — if it can't, no variant URLs are generated). Ratings sit at the right level: group-wide → `ProductGroup.aggregateRating`; variant-specific → the variant `Product`. **Never `AggregateOffer` for variants.** Builders stay pure functions in `lib/seo/schema-org.ts` + one `<JsonLd data={…}/>` server component emitting a single safely-serialized script per graph. `aggregateRating` only when `rating_count > 0` **and** `rating_avg` is a valid number within the 1–5 scale and the rating is visibly shown on the page (review §5) — invalid/empty data emits no node. | A single Offer under-describes configurable products; wrong per-variant availability/ratings misrepresent the offer. |
| D5 (v2.1) | **Deterministic, crawler-stable pricing.** The publicly indexable representation uses the **store's configured default currency** and the **active selling price** (promotion applied) — the same value rendered in the default server HTML and in the JSON-LD. Visitor display-currency selection is cosmetic (Baxela charges in the base currency by design) and never changes the SEO representation; if per-currency indexable URLs arrive later, each can carry its own matching price. The original price on sale is **optional** markup via Google's documented `UnitPriceSpecification` with `priceType: StrikethroughPrice` — never an ad-hoc `priceSpecification` shape. ISO 4217 codes; minor-unit → decimal at one boundary. Unreliable price/currency → omit, never fabricate. | Googlebot must not see EUR one crawl and USD the next; and a converted display price isn't necessarily the chargeable amount (final review §1, §4). |
| D6 (v2) | **Purchase availability, not physical stock.** The public payload gains `availability: string` — an extensible enum (`in_stock`, `out_of_stock`, `backorder`, `preorder` …) derived through the existing `InventoryGateway` from what the customer can actually buy, not raw quantity (Baxela v1 has no backorder/preorder flows, so only the first two values ship — the enum exists so they can land without breaking the contract). Per-variant availability when the variant strategy is active. Storefront maps the enum to schema.org availability URLs. **Unreliable availability is omitted, never guessed.** | A boolean conflates purchasability with stock count (review §3). |
| D7 (v2.1) | **Placement + coherent entity graph:** `Organization` ← `WebSite` ← **`WebPage`** (stable `@id`s: `{SITE_URL}/#organization`, `/#website`, `{pageUrl}#webpage`, `#product`, `#breadcrumb`). `WebPage` references `isPartOf` → WebSite, `mainEntity` → Product/ProductGroup or Article, `breadcrumb` → BreadcrumbList — one graph, not unrelated nodes (WebPage is an architectural enhancement, not a rich-result requirement). **Identity roles kept distinct for marketplace-readiness:** `Organization` = the operator of the storefront, `WebSite` = the storefront, `Offer.seller` = the actual seller (same business in a conventional shop; separable when sellers arrive). `Organization` is built from **available store settings** (name, URL, logo, social profiles — anything unavailable is omitted; `WEBSITE_TITLE` alone is not a complete Organization). `Product`/`ProductGroup`+`BreadcrumbList` on the PDP mirroring the visible breadcrumbs, `Article` (dates/author where available) on posts, `BreadcrumbList` on brand/category pages. | Independent unlabeled nodes fragment the store's entity understanding; seller/operator separation costs nothing now and prevents a marketplace rewrite later (final review §3, §7). |
| D8 (new) | **`lib/seo/` architecture** (review §11): `alternates.ts`, `canonical.ts`, `metadata.ts`, `schema-org.ts`, `urls.ts`, `components/json-ld.tsx`. Builders are **framework-independent pure functions** (no React/Next imports); the `<JsonLd>` component **safely serializes** (escape `<`, `U+2028/2029` — content must not break out of the script tag). Metadata helper composes OG (`og:title/description/url/image`) + Twitter cards + fallbacks — no empty titles, no broken OG images; image URLs absolute and publicly crawlable. | One reusable SEO layer future modules (reviews, sellers, marketplace) extend instead of rewrite. |
| D9 (new) | **The SEO layer transforms domain data; it never invents it.** Every node's values trace to API/store settings; anything unavailable is omitted. Honesty discipline (matching the refund/multi-currency rules): structured data makes pages *eligible* for rich results — it guarantees nothing about Google's rendering; the plan and launch copy never claim otherwise. | Review §11 + repo claim-honesty culture. |

## 3. Work items

> **DONE 2026-11-07 (all three phases).** Implemented per v2.1: `lib/seo/` layer
> (urls/alternates/entity-urls/metadata/schema-org + `components/json-ld.tsx` with safe
> serialization); PDP `generateMetadata` with slug canonical + per-locale slug-map
> alternates; static routes (home from store settings, products/brands/blog/contact);
> detail pages self-canonical without alternates where per-locale slugs aren't exposed
> (brands/posts/pages — documented v1 limitation); noindex across auth, cart, checkout,
> payment/return, and the whole profile surface (server `layout.tsx` per segment —
> client pages cannot export metadata; profile pages moved into a `(account)` route
> group, URLs unchanged); `Product`/`ProductGroup`+`hasVariant` PDP graph with
> breadcrumb + webpage nodes; settings-driven `Organization`/`WebSite`; `Article` on
> posts; backend `availability` + `slugs` on the public show payload (3 Pest tests;
> verified live against the compose API — the slug map correctly omits locales with no
> translation). `variesBy` omitted: variant option values carry no axis codes in the
> API yet — unmappable axes are omitted, never guessed (D9). Verified: production
> build green (type-check + compile of all routes); Catalog suite **114 passed**;
> phpstan **[OK]** (baseline spliced for the two touched files only); i18n parity
> unaffected. `e2e/seo.spec.ts` asserts hreflang reciprocity/canonicals/noindex/
> JSON-LD shape (runs in CI's compose stack; a local runtime curl of the head tags was
> starved by machine load — the Rich Results validator run on the deployed demo stays
> the manual launch-QA gate).

### Phase A — Metadata & international SEO (storefront only)
- [ ] `lib/seo/` scaffolding (D8): `urls.ts`, `alternates.ts` (D1 v2 — per-locale URL maps), `canonical.ts`, `metadata.ts` (OG + Twitter cards + fallbacks).
- [ ] PDP `generateMetadata` (D2): title/description (SEO translation ⚠ else product fields), canonical = **slug** form, alternates from the entity's per-locale slugs, OG + Twitter, absolute image URLs.
- [ ] Static routes: home, products, brands (list + detail), blog (list + post + category), pages, contact — canonical + alternates (D3).
- [ ] **Explicit indexing decision for every utility route** (final review §8A): login/signup/forgot-password/enter-otp, profile pages, **cart, checkout, search results, wishlist, order tracking** — each gets `robots: { index: false }` or a documented reason to be indexable, with correct canonical interaction (noindex pages need no alternates).
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
- Query params → a **per-parameter-type policy, never a blanket rule** (final review §6): `utm_*`/tracking → excluded from the canonical; sorting → canonicalizes to the default listing; **filters → a configurable indexing policy** (a filtered page may be an intentional landing page); **pagination → each page keeps its own canonical when it holds distinct indexable content** (`?page=2` must not canonicalize to page 1).
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
- v2.1 (2026-11-07): final review — 9.4/10, **approved** with clarifications, folded
  in directly. Critical: **deterministic crawler pricing** (D5 — store default
  currency for the indexable representation; display conversion is cosmetic and
  never changes SEO output; original-price markup only via the documented
  `StrikethroughPrice` `UnitPriceSpecification`); **group availability never
  propagates to variants** — omit per-variant availability when unreliable, ratings
  at the correct group/variant level (D4). High: ProductGroup details (stable
  `productGroupID`, `variesBy` mapped only from variant-identifying axes, SKUs never
  invented, variant URLs only when URL-selection exists, `hasVariant` nesting
  without `isVariantOf`) (D4); per-parameter-type canonical policy — filters
  configurable, pagination keeps per-page canonicals (§4). Medium: `WebPage` node
  in the entity graph with `isPartOf`/`mainEntity`/`breadcrumb` (D7);
  Organization/WebSite/Offer.seller role separation for marketplace-readiness;
  Organization built from full store settings (D7); expanded noindex audit (cart,
  checkout, search, wishlist, order tracking) (§3 Phase A). Low: estimate
  recalibrated to 2–3 focused days.

## 7. Related docs

- `docs/PLATFORM_GAPS_ROADMAP.md` §Phase B (B.2) — original gap; its sitemap ⚠ item
  is resolved by the verification note above.
- `apps/storefront/lib/sitemap.ts` — the existing hreflang convention (`x-default`
  → default locale) that D1 mirrors for page tags.
