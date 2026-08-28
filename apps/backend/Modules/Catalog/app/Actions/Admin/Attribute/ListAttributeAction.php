<?php

namespace Modules\Catalog\Actions\Admin\Attribute;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Attribute;
use Modules\Catalog\Schemas\Attribute\AttributeSchema;
use Modules\Catalog\Schemas\Attribute\AttributeTranslationSchema as ATSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListAttributeAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = AttributeSchema::TABLE.'.'.AttributeSchema::ID;

        return QueryBuilder::for(Attribute::class)
            ->allowedFilters(
                AllowedFilter::custom(ATSchema::TITLE, new TranslationTitleFilter),
                AllowedFilter::exact(AttributeSchema::GROUP_ID),
                AllowedFilter::exact(AttributeSchema::DATA_TYPE),
                AllowedFilter::exact(AttributeSchema::IS_FILTERABLE),
            )
            ->allowedSorts(
                AttributeSchema::ID,
                AttributeSchema::POSITION,
                AttributeSchema::CODE,
            )
            ->select([
                $id,
                AttributeSchema::GROUP_ID,
                AttributeSchema::CODE,
                AttributeSchema::DATA_TYPE,
                AttributeSchema::IS_FILTERABLE,
                AttributeSchema::POSITION,
                AttributeSchema::TABLE.'.'.AttributeSchema::CREATED_AT,
                AttributeSchema::TABLE.'.'.AttributeSchema::UPDATED_AT,
            ])
            ->with(
                AttributeSchema::RES_TRANSLATIONS,
            )
            ->withCount(AttributeSchema::RES_VALUES)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
