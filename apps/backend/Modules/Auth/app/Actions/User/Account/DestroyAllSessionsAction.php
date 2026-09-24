<?php

namespace Modules\Auth\Actions\User\Account;

use Illuminate\Http\Request;
use Modules\Auth\Models\User;

class DestroyAllSessionsAction
{
    /**
     * "Sign out everywhere": revokes every token, including the one making
     * the request, so the caller is immediately unauthenticated too.
     */
    public function handle(Request $request): int
    {
        /* @var User $user */
        $user = $request->user();

        return $user->tokens()->delete();
    }
}
