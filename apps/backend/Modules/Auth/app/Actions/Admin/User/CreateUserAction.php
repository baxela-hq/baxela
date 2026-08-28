<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Schemas\User\UserSchema;

class CreateUserAction extends AbstractUserAction
{
    public function handle(UserRequest $request): Model
    {
        $params = $request->validated();
        if ($request->boolean(UserSchema::IS_ACTIVE)) {
            $params[UserSchema::EMAIL_VERIFIED_AT] = $this->model->freshTimestamp();
        }

        $record = $this->model->query()->create($params);

        return $record->fresh();
    }
}
