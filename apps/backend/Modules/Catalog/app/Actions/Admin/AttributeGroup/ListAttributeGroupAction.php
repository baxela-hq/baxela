<?php

namespace Modules\Catalog\Actions\Admin\AttributeGroup;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\AttributeGroup;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupSchema;
use Modules\Catalog\Schemas\AttributeGroup\AttributeGroupTranslationSchema as AGTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListAttributeGroupAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = AttributeGroupSchema::TABLE.'.'.AttributeGroupSchema::ID;

        return QueryBuilder::for(AttributeGroup::class)
            ->allowedFilters(
                AllowedFilter::custom(AGTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                AttributeGroupSchema::ID,
                AttributeGroupSchema::POSITION,
            )
            ->select([
                $id,
                AttributeGroupSchema::POSITION,
                AttributeGroupSchema::TABLE.'.'.AttributeGroupSchema::CREATED_AT,
                AttributeGroupSchema::TABLE.'.'.AttributeGroupSchema::UPDATED_AT,
            ])
            ->with(
                AttributeGroupSchema::RES_TRANSLATIONS,
            )
            ->withCount(AttributeGroupSchema::RES_ATTRIBUTES)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
