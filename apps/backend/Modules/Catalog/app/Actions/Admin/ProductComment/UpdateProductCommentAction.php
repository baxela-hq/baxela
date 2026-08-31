<?php

namespace Modules\Catalog\Actions\Admin\ProductComment;

use Modules\Catalog\Exceptions\ProductComment\InvalidParentException;
use Modules\Catalog\Exceptions\ProductComment\UpdateFailedException;
use Modules\Catalog\Models\ProductComment;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentStatusEnum;
use Throwable;

class UpdateProductCommentAction extends AbstractProductCommentAction
{
    /**
     * The UI sends the whole record, so every field is patched.
     *
     * @throws InvalidParentException|UpdateFailedException
     */
    public function handle(string $id, array $data): ProductComment
    {
        $record = ProductComment::query()->findOrFail($id);

        $parentId = $data[Schema::PARENT_ID] ?? null;
        $this->assertValidParent($data[Schema::PRODUCT_ID], $parentId, $id);

        // replies stay one level deep: a comment that has replies cannot become one itself
        if (! is_null($parentId) && $record->replies()->exists()) {
            throw new InvalidParentException;
        }

        try {
            $record->update([
                Schema::PRODUCT_ID => $data[Schema::PRODUCT_ID],
                Schema::PARENT_ID => $parentId,
                Schema::BODY => $data[Schema::BODY],
                Schema::STATUS => ProductCommentStatusEnum::from($data[Schema::STATUS]),
            ]);
        } catch (Throwable $e) {
            report($e);
            throw new UpdateFailedException;
        }

        $record = $record->fresh([
            Schema::RES_PRODUCT.'.'.ProductSchema::RES_TRANSLATIONS,
        ]);
        $this->enrichWithUserNames([$record]);

        return $record;
    }
}
