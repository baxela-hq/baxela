<?php

namespace Modules\Content\Actions\Admin\Page;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Content\Models\Page;
use Modules\Content\Schemas\Page\PageSchema;
use Modules\Content\Schemas\Page\PageTranslationSchema as PTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListPageAction extends AbstractPageAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = PageSchema::TABLE.'.'.PageSchema::ID;

        return QueryBuilder::for(Page::class)
            ->allowedFilters(
                AllowedFilter::custom(PTSchema::TITLE, new TranslationTitleFilter),
                AllowedFilter::exact(PageSchema::STATUS),
            )
            ->allowedSorts(
                PageSchema::ID,
            )
            ->select([
                $id,
                PageSchema::STATUS,
                PageSchema::TABLE.'.'.PageSchema::CREATED_AT,
                PageSchema::TABLE.'.'.PageSchema::UPDATED_AT,
            ])
            ->with(
                PageSchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
