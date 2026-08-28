<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListUserAction
{
    public function handle(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::exact(UserSchema::ID),
                AllowedFilter::partial(UserSchema::EMAIL),
                AllowedFilter::exact(UserSchema::IS_ACTIVE),
                AllowedFilter::exact(UserSchema::IS_ADMIN),
            )
            ->allowedSorts(
                UserSchema::ID,
            )
            ->orderBy(UserSchema::ID, 'desc')
            ->paginate(10);
    }
}
