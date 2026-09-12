<?php

namespace Modules\Core\Contracts\Gateways\Catalog;

use Illuminate\Support\Collection;

interface CatalogGatewayInterface
{
    /**
     * Displayable product summaries — Catalog's canonical compact
     * product-card representation — keyed by product id. A product is
     * displayable when it resolves and is published; soft-deleted or
     * unpublished products are simply absent from the collection.
     *
     * Consumers should treat an absent id as "currently unavailable"
     * rather than "never existed".
     */
    public function getProductSummaries(array $productIds): Collection;

    /**
     * Variant summaries keyed by variant id — pricing plus the
     * request-language display fields (product title/slug, option-value
     * label). Variants that no longer resolve are absent.
     */
    public function getVariantSummaries(array $variantIds): Collection;

    /**
     * Whether the product can be referenced (exists and is not
     * soft-deleted). Publication status is deliberately irrelevant —
     * this is a weaker check than displayability and exists so that
     * saving still works for products that are merely unpublished.
     */
    public function productExists(int $productId): bool;

    /**
     * Whether the variant can be referenced (exists). Parallels
     * productExists.
     */
    public function variantExists(int $variantId): bool;

    /**
     * The product slug that identifies the ordered product at purchase
     * time — the default-language translation's slug, falling back to any
     * slug — so order-item snapshots keep working as product links even
     * after admin edits recreate variants. Null when the variant no longer
     * resolves.
     */
    public function getProductSlugForVariant(int $variantId): ?string;

    /**
     * Every variant's catalog quantity keyed by variant id — the source of
     * truth Inventory bootstraps its stock ledger from.
     *
     * @return Collection<int, mixed>
     */
    public function variantQuantities(): Collection;
}
