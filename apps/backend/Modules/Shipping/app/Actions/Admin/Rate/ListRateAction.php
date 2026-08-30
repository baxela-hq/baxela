<?php

namespace Modules\Shipping\Actions\Admin\Rate;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Shipping\Models\Rate;
use Modules\Shipping\Schemas\Rate\RateSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListRateAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = RateSchema::TABLE.'.'.RateSchema::ID;

        return QueryBuilder::for(Rate::class)
            ->allowedFilters(
                AllowedFilter::exact(RateSchema::METHOD_ID),
                AllowedFilter::exact(RateSchema::ZONE_ID),
            )
            ->allowedSorts(
                RateSchema::ID,
                RateSchema::PRICE,
            )
            ->select([
                $id,
                RateSchema::METHOD_ID,
                RateSchema::ZONE_ID,
                RateSchema::PRICE,
                RateSchema::TABLE.'.'.RateSchema::CREATED_AT,
                RateSchema::TABLE.'.'.RateSchema::UPDATED_AT,
            ])
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
