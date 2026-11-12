<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Models\User;
use Modules\Auth\Schemas\Token\PersonalAccessTokenSchema;

class ListUserSessionsAction extends AbstractUserAction
{
    /**
     * Every active session (Sanctum token) of the target user.
     *
     * @return Collection<int, PersonalAccessToken>
     */
    public function handle(string $id): Collection
    {
        $user = $this->model->query()->findOrFail($id);

        return $user->tokens()
            ->orderByDesc(PersonalAccessTokenSchema::ID)
            ->get();
    }
}
