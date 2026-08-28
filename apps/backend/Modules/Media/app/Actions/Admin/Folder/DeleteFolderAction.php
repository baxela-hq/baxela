<?php

namespace Modules\Media\Actions\Admin\Folder;

use Modules\Media\Models\Folder;

class DeleteFolderAction
{
    public function handle(string $id): bool
    {
        $record = Folder::query()->findOrFail($id);

        return $record->delete();
    }
}
