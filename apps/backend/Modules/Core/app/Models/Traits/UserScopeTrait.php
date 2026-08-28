<?php

namespace Modules\Core\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Scopes\UserScope;
use Modules\Core\Utils\Auth;

trait UserScopeTrait
{
    protected static function booted(): void
    {
        static::addGlobalScope(new UserScope);

        static::creating(function (Model $model) {
            $model->user_id = Auth::id();
        });
    }
}
