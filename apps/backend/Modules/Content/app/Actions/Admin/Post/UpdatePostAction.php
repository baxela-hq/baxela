<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Support\Facades\DB;
use Modules\Content\Exceptions\Post\UpdateFailedException;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Content\Schemas\Post\PostSchema;
use Throwable;

class UpdatePostAction
{
    use EnrichesPostProductsTrait;
    use FiltersPostSeoTrait;

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
                PostSchema::PUBLISHED_AT => $data[PostSchema::PUBLISHED_AT] ?? null,
            ]);
            $record->categories()->sync($data[PostSchema::RES_CATEGORIES] ?? []);

            $record->seo()->delete();
            foreach ($this->filterPostSeo($data[PostSchema::RES_SEO] ?? []) as $seo) {
                $record->seo()->create($seo);
            }

            $record->translations()->delete();
            foreach ($data[PostSchema::RES_TRANSLATIONS] as $translation) {
                $record->translations()->create($translation);
            }

            $record->products()->delete();
            foreach ($data[PostSchema::RES_PRODUCTS] ?? [] as $productId) {
                $record->products()->create([PostProductSchema::PRODUCT_ID => $productId]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        $record->load(PostSchema::RES_TRANSLATIONS, PostSchema::RES_CATEGORIES, PostSchema::RES_SEO, PostSchema::RES_PRODUCTS);
        $this->enrichWithProductSummaries($record);

        return $record;
    }
}
