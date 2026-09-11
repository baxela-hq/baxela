<?php

namespace Modules\Menu\Actions\Admin\Menu;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Modules\Menu\Models\Menu;
use Modules\Menu\Schemas\Menu\MenuSchema;
use Modules\Menu\Schemas\Menu\MenuTranslationSchema as MTSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListMenuAction extends AbstractMenuAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = MenuSchema::TABLE.'.'.MenuSchema::ID;

        return QueryBuilder::for(Menu::class)
            ->allowedFilters(
                AllowedFilter::exact(MenuSchema::LOCATION),
                AllowedFilter::exact(MenuSchema::IS_ACTIVE),
                AllowedFilter::custom(MTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                MenuSchema::ID,
                MenuSchema::LOCATION,
                MenuSchema::IS_ACTIVE,
                MenuSchema::CREATED_AT,
            )
            ->select([
                $id,
                MenuSchema::LOCATION,
                MenuSchema::IS_ACTIVE,
                MenuSchema::TABLE.'.'.MenuSchema::CREATED_AT,
                MenuSchema::TABLE.'.'.MenuSchema::UPDATED_AT,
            ])
            ->with(
                MenuSchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
