<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;

class ShowProductCommentAction extends AbstractProductCommentAction
{
    public function handle(string $id): ProductComment
    {
        $comment = ProductComment::query()
            ->with([
                Schema::RES_PRODUCT.'.'.ProductSchema::RES_TRANSLATIONS,
                Schema::RES_REPLIES,
            ])
            ->findOrFail($id);

        $this->enrichWithUserNames([$comment]);

        return $comment;
    }
}
