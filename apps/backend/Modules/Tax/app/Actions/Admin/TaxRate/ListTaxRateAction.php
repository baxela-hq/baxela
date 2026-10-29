<?php

namespace Modules\Tax\Actions\Admin\TaxRate;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Utils\Pagination;
use Modules\Tax\Models\TaxRate;
use Modules\Tax\Schemas\TaxRate\TaxRateSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListTaxRateAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = TaxRateSchema::TABLE.'.'.TaxRateSchema::ID;

        return QueryBuilder::for(TaxRate::class)
            ->allowedFilters(
                AllowedFilter::partial(TaxRateSchema::NAME),
                AllowedFilter::exact(TaxRateSchema::COUNTRY_CODE),
                AllowedFilter::exact(TaxRateSchema::TAX_CLASS_ID),
                AllowedFilter::exact(TaxRateSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                TaxRateSchema::ID,
                TaxRateSchema::NAME,
                TaxRateSchema::COUNTRY_CODE,
            )
            ->select([
                $id,
                TaxRateSchema::NAME,
                TaxRateSchema::COUNTRY_CODE,
                TaxRateSchema::TAX_CLASS_ID,
                TaxRateSchema::RATE,
                TaxRateSchema::IS_ACTIVE,
                TaxRateSchema::TABLE.'.'.TaxRateSchema::CREATED_AT,
                TaxRateSchema::TABLE.'.'.TaxRateSchema::UPDATED_AT,
            ])
            ->with(TaxRateSchema::RELATION_TAX_CLASS)
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
