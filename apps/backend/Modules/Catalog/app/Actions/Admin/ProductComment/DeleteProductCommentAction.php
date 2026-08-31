<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Models\ProductComment;

class DeleteProductCommentAction extends AbstractProductCommentAction
{
    public function handle(string $id): bool
    {
        $record = ProductComment::query()->findOrFail($id);

        return $record->delete();
    }
}
