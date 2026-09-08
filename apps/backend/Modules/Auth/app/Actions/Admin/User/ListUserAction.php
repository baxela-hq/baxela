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
        return QueryBuilder::for(User::query()->with(UserSchema::ROLES))
            ->allowedFilters(
                AllowedFilter::exact(UserSchema::ID),
                AllowedFilter::partial(UserSchema::EMAIL),
                AllowedFilter::exact(UserSchema::IS_ACTIVE),
                AllowedFilter::callback(UserSchema::ROLES, function ($query, $value) {
                    $query->whereHas(UserSchema::ROLES, function ($roles) use ($value) {
                        $roles->whereIn('name', (array) $value);
                    });
                }),
            )
            ->allowedSorts(
                UserSchema::ID,
            )
            ->orderBy(UserSchema::ID, 'desc')
            ->paginate(10);
    }
}
