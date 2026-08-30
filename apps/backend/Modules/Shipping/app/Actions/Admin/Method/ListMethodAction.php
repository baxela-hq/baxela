<?php

namespace Modules\Shipping\Actions\Admin\Method;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Shipping\Models\Method;
use Modules\Shipping\Schemas\Method\MethodSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListMethodAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = MethodSchema::TABLE.'.'.MethodSchema::ID;

        return QueryBuilder::for(Method::class)
            ->allowedFilters(
                AllowedFilter::partial(MethodSchema::CODE),
                AllowedFilter::exact(MethodSchema::IS_ACTIVE),
            )
            ->allowedSorts(
                MethodSchema::ID,
                MethodSchema::CODE,
                MethodSchema::POSITION,
            )
            ->select([
                $id,
                MethodSchema::CODE,
                MethodSchema::IS_ACTIVE,
                MethodSchema::POSITION,
                MethodSchema::TABLE.'.'.MethodSchema::CREATED_AT,
                MethodSchema::TABLE.'.'.MethodSchema::UPDATED_AT,
            ])
            ->with(
                MethodSchema::RES_TRANSLATIONS,
            )
            ->orderBy($id, 'desc')
            ->paginate(intval(request()->input('per_page', 15)));
    }
}
