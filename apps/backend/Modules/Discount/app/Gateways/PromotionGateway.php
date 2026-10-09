<?php

namespace Modules\Discount\Gateways;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Discount\DTOs\ProductPromotion;
use Modules\Core\Contracts\Gateways\Discount\PromotionGatewayInterface;
use Modules\Core\Support\Money;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionCategorySchema;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Modules\Discount\Schemas\Promotion\ScopeTypeEnum;

class PromotionGateway implements PromotionGatewayInterface
{
    /**
     * Pure resolution: everything needed to decide the winner — active
     * window, scope coverage, the caller-supplied product→category map —
     * is already in hand, so this method never calls into Catalog.
     */
    public function resolveForProducts(array $productIds, Collection $categoryIdsByProduct): Collection
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if ($productIds === []) {
            return collect();
        }

        $promotions = $this->activePromotions();
        if ($promotions->isEmpty()) {
            return collect();
        }

        [$byProduct, $byCategory, $storeWide] = $this->indexByScope($promotions);

        $resolved = collect();
        foreach ($productIds as $productId) {
            // Specificity ladder: a product-pivot match always wins, then a
            // category match, then store-wide; within a rung the comparator
            // picks priority desc, lower id.
            $candidates = $byProduct->get($productId)
                ?? $this->categoryCandidates($categoryIdsByProduct->get($productId), $byCategory)
                ?? $storeWide;

            $winner = $this->winner($candidates);
            if ($winner !== null) {
                $resolved->put($productId, new ProductPromotion(
                    promotion_id: (int) $winner->{PromotionSchema::ID},
                    type: $winner->{PromotionSchema::TYPE}->value,
                    // Normalize to the exact 2dp major-unit wire format no
                    // matter how the DB driver returns the decimal
                    value: Money::toDecimal(Money::fromDecimal((string) $winner->{PromotionSchema::VALUE})),
                    ends_at: $winner->{PromotionSchema::ENDS_AT}?->toIso8601String(),
                ));
            }
        }

        return $resolved;
    }

    /**
     * Promotions whose is_active flag and UTC window (inclusive bounds,
     * null = unbounded on that side) contain now.
     *
     * @return Collection<int, Promotion>
     */
    private function activePromotions(): Collection
    {
        $now = now();

        return Promotion::query()
            ->where(PromotionSchema::IS_ACTIVE, true)
            ->where(fn ($query) => $query
                ->whereNull(PromotionSchema::STARTS_AT)
                ->orWhere(PromotionSchema::STARTS_AT, '<=', $now))
            ->where(fn ($query) => $query
                ->whereNull(PromotionSchema::ENDS_AT)
                ->orWhere(PromotionSchema::ENDS_AT, '>=', $now))
            ->get();
    }

    /**
     * Batch-load the pivots once and index the promotions by how they can
     * match: per selected product, per selected category, and the
     * store-wide fallback.
     *
     * @return array{0: Collection<int, Collection<int, Promotion>>, 1: Collection<int, Collection<int, Promotion>>, 2: Collection<int, Promotion>}
     */
    private function indexByScope(Collection $promotions): array
    {
        $promotionIds = $promotions->pluck(PromotionSchema::ID)->all();

        // Both pivots are grouped by promotion id so each promotion looks
        // up its own scope selections in one shot
        $productPivot = DB::table(PromotionProductSchema::TABLE)
            ->whereIn(PromotionProductSchema::PROMOTION_ID, $promotionIds)
            ->get()
            ->groupBy(PromotionProductSchema::PROMOTION_ID);

        $categoryPivot = DB::table(PromotionCategorySchema::TABLE)
            ->whereIn(PromotionCategorySchema::PROMOTION_ID, $promotionIds)
            ->get()
            ->groupBy(PromotionCategorySchema::PROMOTION_ID);

        $byProduct = collect();
        $byCategory = collect();
        $storeWide = collect();

        foreach ($promotions as $promotion) {
            if ($promotion->{PromotionSchema::SCOPE} === ScopeTypeEnum::ALL) {
                $storeWide->push($promotion);
                continue;
            }

            $promotionId = $promotion->{PromotionSchema::ID};
            foreach ($productPivot->get($promotionId, collect()) as $row) {
                $productId = (int) $row->{PromotionProductSchema::PRODUCT_ID};
                $byProduct->put($productId, ($byProduct->get($productId) ?? collect())->push($promotion));
            }

            foreach ($categoryPivot->get($promotionId, collect()) as $row) {
                $categoryId = (int) $row->{PromotionCategorySchema::CATEGORY_ID};
                $byCategory->put($categoryId, ($byCategory->get($categoryId) ?? collect())->push($promotion));
            }
        }

        return [$byProduct, $byCategory, $storeWide];
    }

    /**
     * @param  int[]|null  $productCategoryIds
     * @param  Collection<int, Collection<int, Promotion>>  $byCategory
     * @return Collection<int, Promotion>|null
     */
    private function categoryCandidates(?array $productCategoryIds, Collection $byCategory): ?Collection
    {
        if ($productCategoryIds === null || $productCategoryIds === []) {
            return null;
        }

        $candidates = $byCategory->filter(fn ($promotions, int $categoryId) => in_array($categoryId, $productCategoryIds, true))
            ->flatten()
            ->unique(PromotionSchema::ID);

        return $candidates->isNotEmpty() ? $candidates->values() : null;
    }

    /**
     * @param  Collection<int, Promotion>|null  $candidates
     */
    private function winner(?Collection $candidates): ?Promotion
    {
        if ($candidates === null || $candidates->isEmpty()) {
            return null;
        }

        return $candidates->reduce(function (?Promotion $best, Promotion $current): Promotion {
            if ($best === null) {
                return $current;
            }

            $bestRank = [-$best->{PromotionSchema::PRIORITY}, $best->{PromotionSchema::ID}];
            $currentRank = [-$current->{PromotionSchema::PRIORITY}, $current->{PromotionSchema::ID}];

            return (($currentRank <=> $bestRank) === -1) ? $current : $best;
        });
    }
}
