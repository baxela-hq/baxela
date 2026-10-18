<?php

namespace Modules\Catalog\Actions\User\ProductComment;

use Illuminate\Support\Collection;
use Modules\Catalog\Models\ProductCommentReaction;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema as RSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Core\Utils\Auth;

class ListMyProductCommentReactionsAction
{
    /**
     * The authenticated user's reactions across a product's whole
     * comment thread (top-level reviews and replies alike), so the
     * public thread listing can stay unauthenticated.
     *
     * @return Collection<int, ProductCommentReaction>
     */
    public function handle(string $productId): Collection
    {
        return ProductCommentReaction::query()
            ->where(RSchema::USER_ID, Auth::id())
            ->whereHas(RSchema::RELATION_COMMENT, fn ($comment) => $comment
                ->where(Schema::PRODUCT_ID, $productId))
            ->get();
    }
}
