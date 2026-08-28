<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Schemas\User\UserSchema;

class UpdateUserAction extends AbstractUserAction
{
    public function handle(string $id, UserRequest $request): Model
    {
        $record = $this->model->query()->findOrFail($id);
        $params = $request->validated();
        if ($request->boolean(UserSchema::IS_ACTIVE) && ! $record->{UserSchema::IS_ACTIVE}) {
            $params[UserSchema::EMAIL_VERIFIED_AT] = $this->model->freshTimestamp();
        }
        $record->update($params);

        return $record;
    }
}
