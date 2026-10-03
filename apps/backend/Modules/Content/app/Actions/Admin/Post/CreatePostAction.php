<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Support\Facades\DB;
use Modules\Content\Exceptions\Post\CreationFailedException;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\Post\PostSchema;
use Throwable;

class CreatePostAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Post
    {
        try {
            DB::beginTransaction();

            $record = Post::query()->create([
                PostSchema::STATUS => $data[PostSchema::STATUS],
                PostSchema::IS_FEATURED => $data[PostSchema::IS_FEATURED],
            ]);
            $record->categories()->attach($data[PostSchema::RES_CATEGORIES] ?? []);

            foreach ($data[PostSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        $record = $record->refresh();

        return $record->load(PostSchema::RES_TRANSLATIONS, PostSchema::RES_CATEGORIES);
    }
}
