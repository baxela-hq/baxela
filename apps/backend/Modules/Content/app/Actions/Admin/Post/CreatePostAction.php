<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Support\Facades\DB;
use Modules\Content\Exceptions\Post\CreationFailedException;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\Post\PostSchema;
use Throwable;

class CreatePostAction
{
    use FiltersPostSeoTrait;

    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(array $data): Post
    {
        try {
            DB::beginTransaction();

            $record = Post::query()->create([
                PostSchema::STATUS => $data[PostSchema::STATUS],
                PostSchema::PUBLISHED_AT => $data[PostSchema::PUBLISHED_AT] ?? null,
            ]);
            $record->categories()->attach($data[PostSchema::RES_CATEGORIES] ?? []);

            foreach ($data[PostSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            foreach ($data[PostSchema::RES_IMAGES] ?? [] as $image) {
                $record->images()->create($image);
            }

            foreach ($this->filterPostSeo($data[PostSchema::RES_SEO] ?? []) as $seo) {
                $record->seo()->create($seo);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new CreationFailedException;
        }

        $record = $record->refresh();

        return $record->load(PostSchema::RES_TRANSLATIONS, PostSchema::RES_CATEGORIES, PostSchema::RES_IMAGES, PostSchema::RES_SEO);
    }
}
