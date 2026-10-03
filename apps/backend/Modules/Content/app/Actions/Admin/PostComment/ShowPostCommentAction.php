<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;

class ShowPostCommentAction extends AbstractPostCommentAction
{
    public function handle(string $id): PostComment
    {
        $comment = PostComment::query()
            ->with([
                Schema::RES_POST.'.'.PostSchema::RES_TRANSLATIONS,
                Schema::RES_REPLIES,
            ])
            ->findOrFail($id);

        $this->enrichWithUserNames([$comment]);

        return $comment;
    }
}
