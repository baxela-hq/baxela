<?php

namespace Modules\Content\Actions\Public\Post;

use Illuminate\Database\Eloquent\Model;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class ShowPostAction extends AbstractPostAction
{
    /**
     * Resolves the post by numeric id or by translation slug — the
     * storefront links posts by slug. Numeric ids keep working for API
     * consumers.
     */
    public function handle(string $idOrSlug): Model
    {
        $query = $this->model
            ->where(PostSchema::STATUS, PostStatusEnum::PUBLISHED)
            ->with([
                PostSchema::RES_TRANSLATIONS,
                PostSchema::RES_CATEGORIES.'.'.PostCategorySchema::RES_TRANSLATIONS,
            ]);

        if (ctype_digit($idOrSlug)) {
            return $query->findOrFail($idOrSlug);
        }

        return $query
            ->whereHas(
                PostSchema::RES_TRANSLATIONS,
                fn ($translation) => $translation->where(PTSchema::SLUG, $idOrSlug)
            )
            ->firstOrFail();
    }
}
