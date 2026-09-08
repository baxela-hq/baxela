<?php

namespace Modules\Menu\Actions\Admin\MenuLink;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Modules\Menu\Filters\NullableExactFilter;
use Modules\Menu\Models\MenuLink;
use Modules\Menu\Schemas\MenuLink\MenuLinkSchema;
use Modules\Menu\Schemas\MenuLink\MenuLinkTranslationSchema as MLTSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListMenuLinkAction extends AbstractMenuLinkAction
{
    public function handle(string $menuId): LengthAwarePaginator
    {
        $id = MenuLinkSchema::TABLE.'.'.MenuLinkSchema::ID;

        return QueryBuilder::for(MenuLink::class)
            ->allowedFilters(
                AllowedFilter::custom(MenuLinkSchema::PARENT_ID, new NullableExactFilter(MenuLinkSchema::PARENT_ID))
                    ->nullable(true),
                AllowedFilter::custom(MLTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                MenuLinkSchema::ID,
                MenuLinkSchema::POSITION,
            )
            ->select([
                $id,
                MenuLinkSchema::MENU_ID,
                MenuLinkSchema::PARENT_ID,
                MenuLinkSchema::POSITION,
                MenuLinkSchema::URL,
                MenuLinkSchema::TARGET,
                MenuLinkSchema::TABLE.'.'.MenuLinkSchema::CREATED_AT,
                MenuLinkSchema::TABLE.'.'.MenuLinkSchema::UPDATED_AT,
            ])
            ->with(
                MenuLinkSchema::RES_TRANSLATIONS,
            )
            ->where(MenuLinkSchema::MENU_ID, $menuId)
            ->orderBy(MenuLinkSchema::POSITION, 'asc')
            ->orderBy($id, 'asc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
