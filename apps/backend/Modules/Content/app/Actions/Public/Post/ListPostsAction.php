<?php

namespace Modules\Content\Actions\Public\Post;

use Illuminate\Http\Request;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostStatusEnum;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;

class ListPostsAction extends AbstractPostAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->where(PostSchema::STATUS, PostStatusEnum::PUBLISHED)
            // scheduled publishing: a future publish date keeps the post
            // hidden until the moment passes — no scheduler involved
            ->where(function ($query) {
                $query
                    ->whereNull(PostSchema::PUBLISHED_AT)
                    ->orWhere(PostSchema::PUBLISHED_AT, '<=', now());
            })
            ->when($request->input('category'), fn ($query, $categorySlug) => $query
                ->whereHas(
                    PostSchema::RES_CATEGORIES.'.'.PostCategorySchema::RES_TRANSLATIONS,
                    fn ($translation) => $translation->where(PCTSchema::SLUG, $categorySlug)
                ))
            ->with([
                PostSchema::RES_TRANSLATIONS,
                PostSchema::RES_CATEGORIES.'.'.PostCategorySchema::RES_TRANSLATIONS,
                PostSchema::RES_IMAGES,
            ])
            ->orderBy(PostSchema::TABLE.'.'.PostSchema::ID, 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }
}
