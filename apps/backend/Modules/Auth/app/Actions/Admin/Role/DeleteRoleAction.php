<?php

namespace Modules\Auth\Actions\Admin\Role;

use Modules\Auth\Exceptions\Role\InUseException;

class DeleteRoleAction extends AbstractRoleAction
{
    /**
     * @throws InUseException
     */
    public function handle(string $id): bool
    {
        $record = $this->model->query()->findOrFail($id);
        $this->assertNotSuperAdmin($record);

        if ($record->users()->exists()) {
            throw new InUseException;
        }

        return (bool) $record->delete();
    }
}
