<?php

namespace Modules\Catalog\Actions\Admin\AttributeValue;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\AttributeValue;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueSchema;
use Modules\Catalog\Schemas\AttributeValue\AttributeValueTranslationSchema as AVTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListAttributeValueAction
{
    public function handle(string $attributeId): LengthAwarePaginator
    {
        $id = AttributeValueSchema::TABLE.'.'.AttributeValueSchema::ID;

        return QueryBuilder::for(AttributeValue::class)
            ->allowedFilters(
                AllowedFilter::custom(AVTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                AttributeValueSchema::ID,
                AttributeValueSchema::POSITION,
            )
            ->select([
                $id,
                AttributeValueSchema::ATTRIBUTE_ID,
                AttributeValueSchema::POSITION,
                AttributeValueSchema::TABLE.'.'.AttributeValueSchema::CREATED_AT,
                AttributeValueSchema::TABLE.'.'.AttributeValueSchema::UPDATED_AT,
            ])
            ->with(
                AttributeValueSchema::RES_TRANSLATIONS,
            )
            ->where(AttributeValueSchema::ATTRIBUTE_ID, $attributeId)
            ->orderBy(AttributeValueSchema::POSITION)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
