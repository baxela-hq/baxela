<?php

namespace Modules\Catalog\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema;

class CommentReactionCounts
{
    /**
     * withCount shape for the like/dislike tallies exposed on comment
     * payloads (public thread and admin moderation alike).
     *
     * @return array<string, callable>
     */
    public static function withCount(): array
    {
        return [
            ProductCommentSchema::RES_REACTIONS.' as '.ProductCommentSchema::RES_LIKES_COUNT => fn (Builder $query) => $query
                ->where(ProductCommentReactionSchema::REACTION, ProductCommentReactionEnum::LIKE),
            ProductCommentSchema::RES_REACTIONS.' as '.ProductCommentSchema::RES_DISLIKES_COUNT => fn (Builder $query) => $query
                ->where(ProductCommentReactionSchema::REACTION, ProductCommentReactionEnum::DISLIKE),
        ];
    }
}
