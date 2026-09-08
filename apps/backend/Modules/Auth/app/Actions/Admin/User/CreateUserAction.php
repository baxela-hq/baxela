<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Schemas\User\UserSchema;

class CreateUserAction extends AbstractUserAction
{
    public function handle(UserRequest $request): Model
    {
        $params = $request->validated();
        $roleIds = $this->takeRoleIds($params);

        if ($request->boolean(UserSchema::IS_ACTIVE)) {
            $params[UserSchema::EMAIL_VERIFIED_AT] = $this->model->freshTimestamp();
        }

        $record = DB::transaction(function () use ($params, $roleIds): Model {
            $record = $this->model->query()->create($params);

            if ($roleIds !== null) {
                $this->assertRoleAssignmentAllowed($roleIds);
                $record->syncRoles($roleIds);
            }

            return $record;
        });

        return $record->fresh();
    }
}
