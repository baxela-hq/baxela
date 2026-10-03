<?php

namespace Modules\Content\Actions\Admin\PostCategory;

use Modules\Content\Exceptions\PostCategory\CreationFailedException;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Throwable;

class CreatePostCategoryAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): PostCategory
    {
        try {
            $record = PostCategory::query()->create([
                PostCategorySchema::PARENT_ID => $data[PostCategorySchema::PARENT_ID],
                PostCategorySchema::POSITION => $data[PostCategorySchema::POSITION],
            ]);

            foreach ($data[PostCategorySchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }
        } catch (Throwable $e) {
            report($e);
            throw new CreationFailedException;
        }

        $record = $record->refresh();

        return $record->load(PostCategorySchema::RES_TRANSLATIONS);
    }
}
