<?php

namespace Modules\Catalog\Actions\Admin\Option;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Option;
use Modules\Catalog\Schemas\Option\OptionSchema;
use Modules\Catalog\Schemas\Option\OptionTranslationSchema as OTSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListOptionAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = OptionSchema::TABLE.'.'.OptionSchema::ID;

        return QueryBuilder::for(Option::class)
            ->allowedFilters(
                AllowedFilter::partial(OTSchema::TITLE),
            )
            ->allowedSorts(
                OptionSchema::ID,
                OptionSchema::POSITION,
            )
            ->select([
                $id,
                OptionSchema::POSITION,
                OptionSchema::TABLE.'.'.OptionSchema::CREATED_AT,
                OptionSchema::TABLE.'.'.OptionSchema::UPDATED_AT,
            ])
            ->with(
                OptionSchema::RES_TRANSLATIONS,
                // OptionSchema::RES_VALUES.'.'.OptionSchema::RES_TRANSLATIONS
            )
            ->withCount(OptionSchema::RES_VALUES)
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
