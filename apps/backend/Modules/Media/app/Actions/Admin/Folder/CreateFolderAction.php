<?php

namespace Modules\Media\Actions\Admin\Folder;

use Modules\Core\Utils\Auth;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class CreateFolderAction
{
    public function handle(array $data): Folder
    {
        $data[FolderSchema::USER_ID] = Auth::id();

        // nesting is only allowed under folders owned by the same user
        if (! empty($data[FolderSchema::PARENT_ID])) {
            Folder::query()
                ->where(FolderSchema::USER_ID, Auth::id())
                ->findOrFail((int) $data[FolderSchema::PARENT_ID]);
        }

        return Folder::query()->create($data);
    }
}
