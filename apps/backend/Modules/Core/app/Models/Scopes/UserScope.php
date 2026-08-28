<?php

namespace Modules\Core\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Core\Utils\Auth;

class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('user_id', Auth::id());
    }
}
