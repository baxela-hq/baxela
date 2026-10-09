<?php

namespace Modules\Core\Contracts\Gateways\Discount;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\Gateways\Discount\DTOs\ProductPromotion;

interface PromotionGatewayInterface
{
    /**
     * Resolve the winning currently-active promotion per product.
     *
     * The CALLER supplies the product→category mapping — Catalog owns that
     * data and this contract deliberately never calls back into Catalog,
     * keeping the Catalog → Discount resolution path one-directional.
     *
     * Winner selection among all promotions whose scope covers the product
     * and whose window contains now: match specificity (product > category
     * > store-wide), then priority desc, then lower id. One winner per
     * product — promotions never stack with each other.
     *
     * @param  Collection<int, int[]>  $categoryIdsByProduct  product id => its category ids
     * @return Collection<int, ProductPromotion>  keyed by product id; absent = no active promotion
     */
    public function resolveForProducts(array $productIds, Collection $categoryIdsByProduct): Collection;
}
