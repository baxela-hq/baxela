<?php

namespace Modules\Auth\Actions\User\Account;

use Modules\Auth\Models\User;
use Modules\Core\Utils\Auth;

class MeAction
{
    public function handle(): User
    {
        /* @var User $user */
        $user = Auth::user();

        return $user;
    }
}
