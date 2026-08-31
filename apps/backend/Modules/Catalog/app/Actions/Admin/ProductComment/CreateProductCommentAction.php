<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Exceptions\ProductComment\CreationFailedException;
use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Modules\Core\Utils\Auth;
use Throwable;

class CreateProductCommentAction extends AbstractProductCommentAction
{
    /**
     * Admin replies are published immediately and attributed to the admin.
     *
     * @throws InvalidParentException|CreationFailedException
     */
    public function handle(array $data): ProductComment
    {
        $this->assertValidParent($data[Schema::PRODUCT_ID], $data[Schema::PARENT_ID]);

        try {
            $record = ProductComment::query()->create([
                Schema::PRODUCT_ID => $data[Schema::PRODUCT_ID],
                Schema::USER_ID => Auth::id(),
                Schema::PARENT_ID => $data[Schema::PARENT_ID],
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => ProductCommentStatusEnum::APPROVED,
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        return $record;
    }
}
