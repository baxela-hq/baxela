<?php

namespace Modules\Content\Actions\Admin\PostCategory;

use Modules\Content\Exceptions\PostCategory\UpdateFailedException;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Throwable;

class UpdatePostCategoryAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): PostCategory
    {
        $record = PostCategory::query()->findOrFail($id);

        try {
            $record->update([
                PostCategorySchema::PARENT_ID => $data[PostCategorySchema::PARENT_ID],
                PostCategorySchema::POSITION => $data[PostCategorySchema::POSITION],
            ]);

            $record->translations()->delete();
            foreach ($data[PostCategorySchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }
        } catch (Throwable $e) {
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(PostCategorySchema::RES_TRANSLATIONS);
    }
}
