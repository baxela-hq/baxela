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

        // folders are shared across admins: the parent only has to exist
        if (! empty($data[FolderSchema::PARENT_ID])) {
            Folder::query()->findOrFail((int) $data[FolderSchema::PARENT_ID]);
        }

        return Folder::query()->create($data);
    }
}
