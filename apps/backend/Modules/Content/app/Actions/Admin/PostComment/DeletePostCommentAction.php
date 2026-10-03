<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Modules\Content\Models\PostComment;

class DeletePostCommentAction extends AbstractPostCommentAction
{
    public function handle(string $id): bool
    {
        $record = PostComment::query()->findOrFail($id);

        return $record->delete();
    }
}
