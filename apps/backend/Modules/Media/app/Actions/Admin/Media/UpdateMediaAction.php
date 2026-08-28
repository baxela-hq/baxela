<?php

namespace Modules\Media\Actions\Admin\Media;

use Modules\Core\Utils\Auth;
use Modules\Media\Models\Folder;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Folder\FolderSchema;
use Modules\Media\Schemas\Media\MediaSchema;

class UpdateMediaAction extends AbstractMediaAction
{
    public function handle(string $id, array $data): Media
    {
        $userId = Auth::id();

        $media = $this->model->query()->findOrFail($id);

        if (array_key_exists(MediaSchema::FOLDER_ID, $data)) {
            $folderId = $data[MediaSchema::FOLDER_ID];

            if ($folderId !== null) {
                Folder::query()
                    ->where(FolderSchema::USER_ID, $userId)
                    ->findOrFail((int) $folderId);
            }
        }

        $media->update($data);

        return $media->fresh();
    }
}
