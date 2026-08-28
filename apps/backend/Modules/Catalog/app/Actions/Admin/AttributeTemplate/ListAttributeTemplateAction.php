<?php

namespace Modules\Catalog\Actions\Admin\AttributeTemplate;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\AttributeTemplate;
use Modules\Catalog\Schemas\AttributeTemplate\AttributeTemplateSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListAttributeTemplateAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = AttributeTemplateSchema::TABLE.'.'.AttributeTemplateSchema::ID;

        return QueryBuilder::for(AttributeTemplate::class)
            ->allowedFilters(
                AllowedFilter::partial(AttributeTemplateSchema::TITLE),
                AllowedFilter::exact(AttributeTemplateSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                AttributeTemplateSchema::ID,
                AttributeTemplateSchema::POSITION,
                AttributeTemplateSchema::TITLE,
            )
            ->select([
                $id,
                AttributeTemplateSchema::TITLE,
                AttributeTemplateSchema::DESCRIPTION,
                AttributeTemplateSchema::IS_ACTIVE,
                AttributeTemplateSchema::POSITION,
                AttributeTemplateSchema::TABLE.'.'.AttributeTemplateSchema::CREATED_AT,
                AttributeTemplateSchema::TABLE.'.'.AttributeTemplateSchema::UPDATED_AT,
            ])
            ->withCount(AttributeTemplateSchema::RES_GROUPS)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
