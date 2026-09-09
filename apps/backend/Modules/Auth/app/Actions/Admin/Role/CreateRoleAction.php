<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Exceptions\Role\CreationFailedException;
use Modules\Auth\Http\Requests\Admin\Role\RoleRequest;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\GuardsEnum;
use Modules\Auth\Schemas\Role\RoleSchema;
use Throwable;

class CreateRoleAction extends AbstractRoleAction
{
    /**
     * @throws CreationFailedException|Throwable
     */
    public function handle(RoleRequest $request): Role
    {
        $params = $request->validated();
        $permissionIds = $this->takePermissionIds($params);

        $record = DB::transaction(function () use ($params, $permissionIds): Role {
            $record = $this->model->query()->create([
                RoleSchema::NAME => $params[RoleSchema::NAME],
                RoleSchema::GUARD_NAME => GuardsEnum::WEB->value,
            ]);

            if ($permissionIds !== null) {
                $record->syncPermissions($permissionIds);
            }

            return $record;
        });

        return $record->fresh(RoleSchema::PERMISSIONS) ?? $record;
    }
}
