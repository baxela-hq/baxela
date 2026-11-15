<?php

namespace Modules\Media\Actions\Admin\Folder;

use Modules\Core\Utils\Auth;
use Modules\Media\Exceptions\Admin\Folder\CircularMoveException;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class UpdateFolderAction
{
    /**
     * @throws CircularMoveException
     */
    /** @param  array<string, mixed>  $data */
    public function handle(string $id, array $data): Folder
    {
        $folder = Folder::query()
            ->where(FolderSchema::USER_ID, Auth::id())
            ->findOrFail($id);

        if (array_key_exists(FolderSchema::PARENT_ID, $data)) {
            $parentId = $data[FolderSchema::PARENT_ID];

            if ($parentId !== null) {
                $this->ensureValidMove($folder, (int) $parentId);
            }
        }

        $folder->update($data);

        return $folder->fresh();
    }

    /**
     * @throws CircularMoveException
     */
    protected function ensureValidMove(Folder $folder, int $parentId): void
    {
        // folders are shared across admins: the move target only has to exist
        $target = Folder::query()->findOrFail($parentId);

        if ($target->getKey() === $folder->getKey()) {
            throw new CircularMoveException;
        }

        $ancestors = Folder::query()
            ->pluck(FolderSchema::PARENT_ID, FolderSchema::ID);

        $cursor = $target->getKey();

        while ($cursor !== null) {
            if ((int) $cursor === (int) $folder->getKey()) {
                throw new CircularMoveException;
            }

            $cursor = $ancestors->has($cursor) ? $ancestors->get($cursor) : null;
        }
    }
}
