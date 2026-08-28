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

        return Folder::query()->create($data);
    }
}
