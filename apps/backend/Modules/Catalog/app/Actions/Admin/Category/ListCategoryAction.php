<?php

namespace Modules\Catalog\Actions\Admin\Category;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\Category\CategoryTranslationSchema as CTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListCategoryAction extends AbstractCategoryAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = CategorySchema::TABLE.'.'.CategorySchema::ID;

        return QueryBuilder::for(Category::class)
            ->allowedFilters(
                AllowedFilter::custom(CTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                CategorySchema::ID,
                CategorySchema::PARENT_ID,
                CategorySchema::POSITION,
            )
            ->select([
                $id,
                CategorySchema::PARENT_ID,
                CategorySchema::POSITION,
                CategorySchema::TABLE.'.'.CategorySchema::CREATED_AT,
                CategorySchema::TABLE.'.'.CategorySchema::UPDATED_AT,
            ])
            ->with(
                CategorySchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
