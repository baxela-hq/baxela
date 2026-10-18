<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Support\CommentReactionCounts;

class ShowProductCommentAction extends AbstractProductCommentAction
{
    public function handle(string $id): ProductComment
    {
        $comment = ProductComment::query()
            ->withCount(CommentReactionCounts::withCount())
            ->with([
                Schema::RES_PRODUCT.'.'.ProductSchema::RES_TRANSLATIONS,
                Schema::RES_REPLIES => fn ($replies) => $replies
                    ->withCount(CommentReactionCounts::withCount()),
            ])
            ->findOrFail($id);

        $this->enrichWithUserNames([$comment]);

        return $comment;
    }
}
