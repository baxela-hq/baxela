<?php

namespace Modules\Auth\Actions\User\Account;

use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class SignOutAction
{
    public function handle(Request $request): bool
    {
        $accessToken = $request->user()?->currentAccessToken();

        if (! $accessToken instanceof PersonalAccessToken) {
            return false;
        }

        return (bool) $accessToken->delete();
    }
}
