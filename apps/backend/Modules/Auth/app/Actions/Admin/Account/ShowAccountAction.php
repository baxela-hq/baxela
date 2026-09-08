<?php

namespace Modules\Auth\Actions\Admin\Account;

use Illuminate\Http\Request;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\User\UserSchema;
use Modules\Core\Utils\Auth;

class ShowAccountAction
{
    public function handle(Request $request): User
    {
        return User::query()
            ->with(UserSchema::ROLES)
            ->findOrFail(Auth::id());
    }
}
