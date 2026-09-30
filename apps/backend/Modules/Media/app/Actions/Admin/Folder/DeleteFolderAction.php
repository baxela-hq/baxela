<?php

namespace Modules\Media\Actions\Admin\Folder;

use Modules\Core\Utils\Auth;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class DeleteFolderAction
{
    public function handle(string $id): bool
    {
        $record = Folder::query()
            ->where(FolderSchema::USER_ID, Auth::id())
            ->findOrFail($id);

        return $record->delete();
    }
}
