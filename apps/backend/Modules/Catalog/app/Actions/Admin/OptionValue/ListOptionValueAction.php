<?php

namespace Modules\Catalog\Actions\Admin\OptionValue;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\OptionValue\OptionValueTranslationSchema as OVTSchema;
use Modules\Core\Repositories\Filter\TranslationTitleFilter;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListOptionValueAction
{
    public function handle(string $optionId): LengthAwarePaginator
    {
        $id = OptionValueSchema::TABLE.'.'.OptionValueSchema::ID;

        return QueryBuilder::for(OptionValue::class)
            ->allowedFilters(
                AllowedFilter::custom(OVTSchema::TITLE, new TranslationTitleFilter),
            )
            ->allowedSorts(
                OptionValueSchema::ID,
                OptionValueSchema::POSITION,
            )
            ->select([
                $id,
                OptionValueSchema::OPTION_ID,
                OptionValueSchema::POSITION,
                OptionValueSchema::TABLE.'.'.OptionValueSchema::CREATED_AT,
                OptionValueSchema::TABLE.'.'.OptionValueSchema::UPDATED_AT,
            ])
            ->with(
                OptionValueSchema::RES_TRANSLATIONS,
            )
            ->withCount(OptionValueSchema::RES_VARIANTS)
            ->where(OptionValueSchema::OPTION_ID, $optionId)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
