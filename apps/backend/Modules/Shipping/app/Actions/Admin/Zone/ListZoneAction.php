<?php

namespace Modules\Shipping\Actions\Admin\Zone;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Shipping\Models\Zone;
use Modules\Shipping\Schemas\Zone\ZoneSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListZoneAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = ZoneSchema::TABLE.'.'.ZoneSchema::ID;

        return QueryBuilder::for(Zone::class)
            ->allowedFilters(
                AllowedFilter::partial(ZoneSchema::NAME),
                AllowedFilter::exact(ZoneSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                ZoneSchema::ID,
                ZoneSchema::NAME,
                ZoneSchema::POSITION,
            )
            ->select([
                $id,
                ZoneSchema::NAME,
                ZoneSchema::IS_ACTIVE,
                ZoneSchema::POSITION,
                ZoneSchema::TABLE.'.'.ZoneSchema::CREATED_AT,
                ZoneSchema::TABLE.'.'.ZoneSchema::UPDATED_AT,
            ])
            ->with(
                ZoneSchema::RES_COUNTRIES,
            )
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
