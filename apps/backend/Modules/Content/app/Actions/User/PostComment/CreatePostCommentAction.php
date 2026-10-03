<?php

namespace Modules\Content\Actions\User\PostComment;

use Modules\Content\Exceptions\PostComment\CreationFailedException;
use Modules\Content\Exceptions\PostComment\InvalidParentException;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Schemas\PostComment\PostCommentStatusEnum;
use Modules\Core\Contracts\Events\Content\PostCommentCreatedEvent;
use Modules\Core\Utils\Auth;
use Throwable;

class CreatePostCommentAction
{
    /**
     * @throws InvalidParentException|CreationFailedException
     */
    public function handle(string $postId, array $data): PostComment
    {
        $parentId = $data[Schema::PARENT_ID] ?? null;
        if (! is_null($parentId)) {
            $parent = PostComment::query()->find($parentId);
            if (is_null($parent)
                || ! is_null($parent->{Schema::PARENT_ID})
                || (int) $parent->{Schema::POST_ID} !== (int) $postId) {
                throw new InvalidParentException;
            }
        }

        try {
            $record = PostComment::query()->create([
                Schema::POST_ID => $postId,
                Schema::USER_ID => Auth::id(),
                Schema::PARENT_ID => $parentId,
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => PostCommentStatusEnum::PENDING,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        event(PostCommentCreatedEvent::fill($record->toArray()));

        return $record;
    }
}
