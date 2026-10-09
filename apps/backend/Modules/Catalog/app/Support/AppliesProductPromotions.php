<?php

namespace Modules\Catalog\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Category\CategoryProductSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Variant\VariantSchema;
use Modules\Core\Contracts\Gateways\Discount\DTOs\ProductPromotion;
use Modules\Core\Contracts\Gateways\Discount\PromotionGatewayInterface;
use Modules\Core\Support\Money;

/**
 * Promotes product prices in memory for a read path: one batched resolver
 * call decides the winning promotion per product, then the hydrated
 * variant models carry the effective price and the original base moves
 * into compare_price. Nothing is ever persisted — base prices in storage
 * are the source of truth and the promotion is re-resolved on every read.
 */
class AppliesProductPromotions
{
    public function __construct(protected PromotionGatewayInterface $promotionGateway) {}

    /**
     * Resolve AND apply in one pass. Products without a winning promotion
     * are left untouched. Returns the resolved map so callers that only
     * need the winners (summary builders) can reuse it.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ProductPromotion>  keyed by product id
     */
    public function apply(Collection $products): Collection
    {
        $promotions = $this->resolve($products);
        if ($promotions->isEmpty()) {
            return $promotions;
        }

        foreach ($products as $product) {
            $promotion = $promotions->get((int) $product->{ProductSchema::ID});
            if ($promotion === null) {
                continue;
            }

            $product->setAttribute(ProductSchema::ATTR_PROMOTION, $promotion);

            foreach ($product->{ProductSchema::RES_VARIANTS} as $variant) {
                $this->applyToVariant($variant, $promotion);
            }
        }

        return $promotions;
    }

    /**
     * Batch-resolve the winning promotion per product. The product→category
     * map comes from eager-loaded relations when present and otherwise from
     * exactly one pivot query — never a query per product.
     *
     * @param  Collection<int, Product>  $products
     * @return Collection<int, ProductPromotion>  keyed by product id
     */
    public function resolve(Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        $productIds = $products->pluck(ProductSchema::ID)->map(fn ($id) => (int) $id)->all();

        /** @var Collection<int, int[]> $categoryMap */
        $categoryMap = collect();
        $missing = [];
        foreach ($products as $product) {
            $productId = (int) $product->{ProductSchema::ID};

            if ($product->relationLoaded(ProductSchema::RES_CATEGORIES)) {
                $categoryMap->put($productId, $product->{ProductSchema::RES_CATEGORIES}
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all());
            } else {
                $categoryMap->put($productId, []);
                $missing[] = $productId;
            }
        }

        if ($missing !== []) {
            DB::table(CategoryProductSchema::TABLE)
                ->whereIn(CategoryProductSchema::PRODUCT_ID, $missing)
                ->get()
                ->each(function ($row) use ($categoryMap): void {
                    $productId = (int) $row->{CategoryProductSchema::PRODUCT_ID};
                    $categoryMap->put($productId, array_merge(
                        $categoryMap->get($productId, []),
                        [(int) $row->{CategoryProductSchema::CATEGORY_ID}],
                    ));
                });
        }

        return $this->promotionGateway->resolveForProducts($productIds, $categoryMap);
    }

    /**
     * While promoted, the reference price shown to customers is the true
     * pre-sale base — the variant's configured compare_price is a static
     * display anchor and is deliberately superseded for the promotion's
     * duration.
     */
    private function applyToVariant($variant, ProductPromotion $promotion): void
    {
        $base = $variant->{VariantSchema::PRICE};
        if ($base === null) {
            return;
        }

        // Normalize once — the DB driver may hand back a native numeric
        // (e.g. 100) while the wire format is the exact 2dp decimal string
        $base = Money::toDecimal(Money::fromDecimal((string) $base));

        $promoted = $promotion->applyTo($base);

        $variant->setAttribute(VariantSchema::PRICE, Money::toDecimal($promoted->effective_minor));
        $variant->setAttribute(VariantSchema::COMPARE_PRICE, $base);
        $variant->setAttribute(VariantSchema::ATTR_PROMOTION, $promotion);
    }
}
