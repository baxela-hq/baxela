<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Modules\Content\Exceptions\PostComment\InvalidParentException;
use Modules\Content\Exceptions\PostComment\UpdateFailedException;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Throwable;

class UpdatePostCommentAction extends AbstractPostCommentAction
{
    /**
     * The UI sends the whole record, so every field is patched.
     *
     * @throws InvalidParentException|UpdateFailedException
     */
    public function handle(string $id, array $data): PostComment
    {
        $record = PostComment::query()->findOrFail($id);

        $parentId = $data[Schema::PARENT_ID] ?? null;
        $this->assertValidParent($data[Schema::POST_ID], $parentId, $id);

        // replies stay one level deep: a comment that has replies cannot become one itself
        if (! is_null($parentId) && $record->replies()->exists()) {
            throw new InvalidParentException;
        }

        try {
            $record->update([
                Schema::POST_ID => $data[Schema::POST_ID],
                Schema::PARENT_ID => $parentId,
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => PostCommentStatusEnum::from($data[Schema::STATUS]),
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new UpdateFailedException;
        }

        $record = $record->fresh([
            Schema::RES_POST.'.'.PostSchema::RES_TRANSLATIONS,
        ]);
        $this->enrichWithUserNames([$record]);

        return $record;
    }
}
