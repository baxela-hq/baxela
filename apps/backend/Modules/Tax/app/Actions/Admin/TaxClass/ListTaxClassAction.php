<?php

namespace Modules\Tax\Actions\Admin\TaxClass;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Tax\Models\TaxClass;
use Modules\Tax\Schemas\TaxClass\TaxClassSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListTaxClassAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = TaxClassSchema::TABLE.'.'.TaxClassSchema::ID;

        return QueryBuilder::for(TaxClass::class)
            ->allowedFilters(
                AllowedFilter::partial(TaxClassSchema::NAME),
                AllowedFilter::exact(TaxClassSchema::IS_DEFAULT),
                AllowedFilter::exact(TaxClassSchema::IS_SHIPPING),
            )
            ->allowedSorts(
                TaxClassSchema::ID,
                TaxClassSchema::NAME,
            )
            ->select([
                $id,
                TaxClassSchema::NAME,
                TaxClassSchema::IS_DEFAULT,
                TaxClassSchema::IS_SHIPPING,
                TaxClassSchema::TABLE.'.'.TaxClassSchema::CREATED_AT,
                TaxClassSchema::TABLE.'.'.TaxClassSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
