<?php

namespace Modules\Discount\Actions\Admin\Promotion;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Core\Utils\Pagination;
use Modules\Discount\Models\Promotion;
use Modules\Discount\Schemas\Promotion\PromotionCategorySchema;
use Modules\Discount\Schemas\Promotion\PromotionProductSchema;
use Modules\Discount\Schemas\Promotion\PromotionSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPromotionAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = PromotionSchema::TABLE.'.'.PromotionSchema::ID;

        $page = QueryBuilder::for(Promotion::class)
            ->allowedFilters(
                AllowedFilter::partial(PromotionSchema::NAME),
                AllowedFilter::exact(PromotionSchema::SCOPE),
                AllowedFilter::exact(PromotionSchema::TYPE),
                AllowedFilter::exact(PromotionSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                PromotionSchema::ID,
                PromotionSchema::NAME,
                PromotionSchema::PRIORITY,
                PromotionSchema::ENDS_AT,
            )
            ->select([
                $id,
                PromotionSchema::NAME,
                PromotionSchema::SCOPE,
                PromotionSchema::TYPE,
                PromotionSchema::VALUE,
                PromotionSchema::STARTS_AT,
                PromotionSchema::ENDS_AT,
                PromotionSchema::PRIORITY,
                PromotionSchema::IS_ACTIVE,
                PromotionSchema::TABLE.'.'.PromotionSchema::CREATED_AT,
                PromotionSchema::TABLE.'.'.PromotionSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());

        // Scope selections for the whole page in two queries — the resource
        // reads them off the attached attributes instead of querying per row
        $promotionIds = $page->getCollection()->pluck(PromotionSchema::ID)->all();

        $productMap = DB::table(PromotionProductSchema::TABLE)
            ->whereIn(PromotionProductSchema::PROMOTION_ID, $promotionIds)
            ->get()
            ->groupBy(PromotionProductSchema::PROMOTION_ID);

        $categoryMap = DB::table(PromotionCategorySchema::TABLE)
            ->whereIn(PromotionCategorySchema::PROMOTION_ID, $promotionIds)
            ->get()
            ->groupBy(PromotionCategorySchema::PROMOTION_ID);

        $page->getCollection()->each(function (Promotion $promotion) use ($productMap, $categoryMap): void {
            $promotion->setAttribute(
                PromotionSchema::ATTR_PRODUCT_IDS,
                $productMap->get($promotion->{PromotionSchema::ID}, collect())
                    ->pluck(PromotionProductSchema::PRODUCT_ID)
                    ->map(fn ($pid) => (int) $pid)
                    ->sort()
                    ->values()
                    ->all(),
            )->setAttribute(
                PromotionSchema::ATTR_CATEGORY_IDS,
                $categoryMap->get($promotion->{PromotionSchema::ID}, collect())
                    ->pluck(PromotionCategorySchema::CATEGORY_ID)
                    ->map(fn ($cid) => (int) $cid)
                    ->sort()
                    ->values()
                    ->all(),
            );
        });

        return $page;
    }
}
