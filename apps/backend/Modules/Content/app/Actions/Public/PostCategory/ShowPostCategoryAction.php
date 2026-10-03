<?php

namespace Modules\Content\Actions\Public\PostCategory;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;

class ShowPostCategoryAction extends AbstractPostCategoryAction
{
    /**
     * Resolves the category by numeric id or by translation slug — the
     * storefront links categories by slug. Numeric ids keep working for
     * API consumers.
     */
    public function handle(string $idOrSlug): Model
    {
        $query = $this->model->with(PostCategorySchema::RES_TRANSLATIONS);

        if (ctype_digit($idOrSlug)) {
            return $query->findOrFail($idOrSlug);
        }

        return $query
            ->whereHas(
                PostCategorySchema::RES_TRANSLATIONS,
                fn ($translation) => $translation->where(PCTSchema::SLUG, $idOrSlug)
            )
            ->firstOrFail();
    }
}
