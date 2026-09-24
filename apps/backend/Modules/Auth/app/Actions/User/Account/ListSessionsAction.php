<?php

namespace Modules\Auth\Actions\User\Account;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;

class ListSessionsAction
{
    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function handle(Request $request): Collection
    {
        /* @var User $user */
        $user = $request->user();

        return $user->tokens()
            ->orderByDesc(PersonalAccessTokenSchema::ID)
            ->get();
    }
}
