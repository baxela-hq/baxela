<?php

namespace Modules\Auth\Actions\Admin\Role;

use Illuminate\Support\Facades\DB;
use Modules\Auth\Exceptions\Role\UpdateFailedException;
use Modules\Auth\Http\Requests\Admin\Role\RoleRequest;
use Modules\Auth\Models\Role;
use Modules\Auth\Schemas\Role\RoleSchema;
use Throwable;

class UpdateRoleAction extends AbstractRoleAction
{
    /**
     * @throws UpdateFailedException|Throwable
     */
    public function handle(string $id, RoleRequest $request): Role
    {
        $record = $this->model->query()->findOrFail($id);
        $this->assertNotSuperAdmin($record);

        $params = $request->validated();
        $permissionIds = $this->takePermissionIds($params);

        try {
            DB::beginTransaction();

            $record->update([
                RoleSchema::NAME => $params[RoleSchema::NAME],
            ]);

            if ($permissionIds !== null) {
                $record->syncPermissions($permissionIds);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            throw new UpdateFailedException;
        }

        return $record->fresh(RoleSchema::PERMISSIONS) ?? $record;
    }
}
