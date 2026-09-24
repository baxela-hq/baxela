<?php

namespace Modules\Auth\Actions\User\Account;

use Illuminate\Http\Request;
use Modules\Auth\Models\User;

class DestroySessionAction
{
    /**
     * Revoke another one of the user's sessions. The query is scoped to the
     * authenticated user's own tokens, so foreign session ids 404 instead of
     * leaking their existence.
     */
    public function handle(Request $request, string $id): bool
    {
        /* @var User $user */
        $user = $request->user();

        $session = $user->tokens()->whereKey($id)->firstOrFail();

        return (bool) $session->delete();
    }
}
