<?php

namespace Modules\Catalog\Support;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Catalog\Search\SearchOutbox;

class RecalculatesProductRating
{
    /**
     * Refresh a product's denormalized review aggregate from its approved,
     * top-level, rated comments. Run after any moderation change that can
     * alter the pool (status, rating, parent move or deletion).
     */
    public static function recalculate(int|string $productId): void
    {
        $aggregate = ProductComment::query()
            ->where(ProductCommentSchema::PRODUCT_ID, $productId)
            ->whereNull(ProductCommentSchema::PARENT_ID)
            ->where(ProductCommentSchema::STATUS, ProductCommentStatusEnum::APPROVED)
            ->whereNotNull(ProductCommentSchema::RATING)
            ->toBase()
            ->selectRaw(
                'COUNT(*) as '.ProductSchema::RATING_COUNT
                .', AVG(`'.ProductCommentSchema::RATING.'`) as '.ProductSchema::RATING_AVG
            )
            ->first();

        // Explicit transaction + search journal: rating changes reach the
        // search index through the same durable path as other mutations.
        DB::transaction(function () use ($productId, $aggregate): void {
            Product::withoutTimestamps(function () use ($productId, $aggregate): void {
                Product::query()
                    ->where(ProductSchema::ID, $productId)
                    ->update([
                        ProductSchema::RATING_COUNT => (int) ($aggregate?->{ProductSchema::RATING_COUNT} ?? 0),
                        ProductSchema::RATING_AVG => $aggregate?->{ProductSchema::RATING_COUNT} > 0
                            ? round((float) $aggregate->{ProductSchema::RATING_AVG}, 2)
                            : null,
                    ]);
            });

            app(SearchOutbox::class)->record((int) $productId);
        });
    }
}
