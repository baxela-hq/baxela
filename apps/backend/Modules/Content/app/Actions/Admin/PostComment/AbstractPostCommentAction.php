<?php

namespace Modules\Content\Actions\Admin\PostComment;

use Modules\Content\Exceptions\PostComment\InvalidParentException;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;

abstract class AbstractPostCommentAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    /**
     * A valid reply target exists, is top-level and belongs to the same post.
     *
     * @throws InvalidParentException
     */
    protected function assertValidParent(int|string $postId, int|string|null $parentId, int|string|null $selfId = null): void
    {
        if (is_null($parentId)) {
            return;
        }

        if (! is_null($selfId) && (int) $parentId === (int) $selfId) {
            throw new InvalidParentException;
        }

        $parent = PostComment::query()->find($parentId);
        if (is_null($parent)
            || ! is_null($parent->{Schema::PARENT_ID})
            || (int) $parent->{Schema::POST_ID} !== (int) $postId) {
            throw new InvalidParentException;
        }
    }

    protected function enrichWithUserNames(iterable $comments): void
    {
        $flattened = collect();
        foreach ($comments as $comment) {
            $flattened->push($comment);
            if ($comment->relationLoaded(Schema::RES_REPLIES)) {
                $flattened = $flattened->merge($comment->getRelation(Schema::RES_REPLIES));
            }
        }

        $names = $this->userGateway->getUserNamesByIds(
            $flattened->pluck(Schema::USER_ID)->unique()->values()->all()
        );

        $flattened->each(function (PostComment $comment) use ($names) {
            $comment->setAttribute(Schema::RES_USER, [
                Schema::ID => $comment->{Schema::USER_ID},
                Schema::RES_USER_NAME => $names[$comment->{Schema::USER_ID}] ?? null,
            ]);
        });
    }
}
