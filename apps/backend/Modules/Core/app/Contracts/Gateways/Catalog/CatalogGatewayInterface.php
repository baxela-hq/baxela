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
     * Whether the product can be referenced (exists and is not
     * soft-deleted). Publication status is deliberately irrelevant —
     * this is a weaker check than displayability and exists so that
     * saving still works for products that are merely unpublished.
     */
    public function productExists(int $productId): bool;
}
