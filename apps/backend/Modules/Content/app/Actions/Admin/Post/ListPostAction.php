<?php

namespace Modules\Content\Actions\Admin\Post;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Content\Models\Post;
use Modules\Content\Schemas\Post\PostSchema;
use Modules\Content\Schemas\Post\PostTranslationSchema as PTSchema;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPostAction extends AbstractPostAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = PostSchema::TABLE.'.'.PostSchema::ID;

        return QueryBuilder::for(Post::class)
            ->allowedFilters(
                AllowedFilter::custom(PTSchema::TITLE, new TranslationTitleFilter),
                AllowedFilter::exact(PostSchema::STATUS),
                AllowedFilter::exact(PostSchema::RES_CATEGORIES.'.'.PostCategorySchema::ID),
            )
            ->allowedSorts(
                PostSchema::ID,
            )
            ->select([
                $id,
                PostSchema::STATUS,
                PostSchema::TABLE.'.'.PostSchema::CREATED_AT,
                PostSchema::TABLE.'.'.PostSchema::UPDATED_AT,
            ])
            ->with(
                PostSchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
