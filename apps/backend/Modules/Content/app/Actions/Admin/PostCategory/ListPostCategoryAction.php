<?php

namespace Modules\Content\Actions\Admin\PostCategory;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Content\Models\PostCategory;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;
use Modules\Content\Schemas\PostCategory\PostCategoryTranslationSchema as PCTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPostCategoryAction extends AbstractPostCategoryAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = PostCategorySchema::TABLE.'.'.PostCategorySchema::ID;

        return QueryBuilder::for(PostCategory::class)
            ->allowedFilters(
                AllowedFilter::custom(PCTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                PostCategorySchema::ID,
                PostCategorySchema::PARENT_ID,
                PostCategorySchema::POSITION,
            )
            ->select([
                $id,
                PostCategorySchema::PARENT_ID,
                PostCategorySchema::POSITION,
                PostCategorySchema::TABLE.'.'.PostCategorySchema::CREATED_AT,
                PostCategorySchema::TABLE.'.'.PostCategorySchema::UPDATED_AT,
            ])
            ->with(
                PostCategorySchema::RES_TRANSLATIONS,
            )
            ->orderByRaw(PostCategorySchema::POSITION.' IS NULL')
            ->orderBy(PostCategorySchema::POSITION)
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
