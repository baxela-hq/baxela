<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Modules\Content\Exceptions\PostComment\CreationFailedException;
use Modules\Content\Exceptions\PostComment\InvalidParentException;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Core\Utils\Auth;
use Throwable;

class CreatePostCommentAction extends AbstractPostCommentAction
{
    /**
     * Admin replies are published immediately and attributed to the admin.
     *
     * @throws InvalidParentException|CreationFailedException
     */
    public function handle(array $data): PostComment
    {
        $this->assertValidParent($data[Schema::POST_ID], $data[Schema::PARENT_ID]);

        try {
            $record = PostComment::query()->create([
                Schema::POST_ID => $data[Schema::POST_ID],
                Schema::USER_ID => Auth::id(),
                Schema::PARENT_ID => $data[Schema::PARENT_ID],
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => PostCommentStatusEnum::APPROVED,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
