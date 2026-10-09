<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Support\Facades\DB;
use Modules\Content\Exceptions\Post\UpdateFailedException;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\Post\PostSchema;
use Throwable;

class UpdatePostAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, array $data): Post
    {
        $record = Post::query()->findOrFail($id);

        try {
            DB::beginTransaction();

            $record->update([
                PostSchema::STATUS => $data[PostSchema::STATUS],
            ]);
            $record->categories()->sync($data[PostSchema::RES_CATEGORIES] ?? []);

            $record->images()->delete();
            foreach ($data[PostSchema::RES_IMAGES] ?? [] as $image) {
                $record->images()->create($image);
            }

            $record->translations()->delete();
            foreach ($data[PostSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record->load(PostSchema::RES_TRANSLATIONS, PostSchema::RES_CATEGORIES, PostSchema::RES_IMAGES);
    }
}
