<?php

namespace Modules\Auth\Actions\Admin\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Http\Requests\Admin\User\UserRequest;
use Modules\Auth\Schemas\User\UserSchema;

class UpdateUserAction extends AbstractUserAction
{
    public function handle(string $id, UserRequest $request): Model
    {
        $record = $this->model->query()->findOrFail($id);
        $params = $request->validated();
        $roleIds = $this->takeRoleIds($params);

        if ($request->boolean(UserSchema::IS_ACTIVE) && ! $record->{UserSchema::IS_ACTIVE}) {
            $params[UserSchema::EMAIL_VERIFIED_AT] = $this->model->freshTimestamp();
        }

        DB::transaction(function () use ($record, $params, $roleIds): void {
            $record->update($params);

            if ($roleIds !== null) {
                $this->assertRoleAssignmentAllowed($roleIds, $record);
                $record->syncRoles($roleIds);
            }
        });

        return $record;
    }
}
