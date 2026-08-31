<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Core\Contracts\Gateways\User\UserGatewayInterface;

abstract class AbstractProductCommentAction
{
    public function __construct(protected UserGatewayInterface $userGateway) {}

    /**
     * A valid reply target exists, is top-level and belongs to the same product.
     *
     * @throws InvalidParentException
     */
    protected function assertValidParent(int|string $productId, int|string|null $parentId, int|string|null $selfId = null): void
    {
        if (is_null($parentId)) {
            return;
        }

        if (! is_null($selfId) && (int) $parentId === (int) $selfId) {
            throw new InvalidParentException;
        }

        $parent = ProductComment::query()->find($parentId);
        if (is_null($parent)
            || ! is_null($parent->{Schema::PARENT_ID})
            || (int) $parent->{Schema::PRODUCT_ID} !== (int) $productId) {
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

        $flattened->each(function (ProductComment $comment) use ($names) {
            $comment->setAttribute(Schema::RES_USER, [
                Schema::ID => $comment->{Schema::USER_ID},
                Schema::RES_USER_NAME => $names[$comment->{Schema::USER_ID}] ?? null,
            ]);
        });
    }
}
