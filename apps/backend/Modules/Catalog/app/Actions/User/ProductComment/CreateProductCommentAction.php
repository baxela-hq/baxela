<?php

namespace Modules\Catalog\Actions\User\ProductComment;

use Modules\Catalog\Exceptions\ProductComment\CreationFailedException;
use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Core\Contracts\Events\Catalog\ProductCommentCreatedEvent;
use Modules\Core\Utils\Auth;
use Throwable;

class CreateProductCommentAction
{
    /**
     * @throws InvalidParentException|CreationFailedException
     */
    public function handle(string $productId, array $data): ProductComment
    {
        $parentId = $data[Schema::PARENT_ID] ?? null;
        if (! is_null($parentId)) {
            $parent = ProductComment::query()->find($parentId);
            if (is_null($parent)
                || ! is_null($parent->{Schema::PARENT_ID})
                || (int) $parent->{Schema::PRODUCT_ID} !== (int) $productId) {
                throw new InvalidParentException;
            }
        }

        try {
            $record = ProductComment::query()->create([
                Schema::PRODUCT_ID => $productId,
                Schema::USER_ID => Auth::id(),
                Schema::PARENT_ID => $parentId,
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => ProductCommentStatusEnum::PENDING,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        event(ProductCommentCreatedEvent::fill($record->toArray()));

        return $record;
    }
}
