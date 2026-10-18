<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Support\RecalculatesProductRating;

class DeleteProductCommentAction extends AbstractProductCommentAction
{
    public function handle(string $id): bool
    {
        $record = ProductComment::query()->findOrFail($id);

        $deleted = $record->delete();

        RecalculatesProductRating::recalculate($record->{Schema::PRODUCT_ID});

        return $deleted;
    }
}
