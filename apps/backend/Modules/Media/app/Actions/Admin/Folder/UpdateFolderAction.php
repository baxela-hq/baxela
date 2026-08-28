<?php

namespace Modules\Media\Actions\Admin\Folder;

use Illuminate\Support\Collection;
use Modules\Media\Exceptions\Admin\Folder\CircularMoveException;
use Modules\Media\Models\Folder;
use Modules\Media\Schemas\Folder\FolderSchema;

class UpdateFolderAction
{
    /**
     * @throws CircularMoveException
     */
    public function handle(string $id, array $data): Folder
    {
        $folder = Folder::query()->findOrFail($id);

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
        $target = Folder::query()
            ->findOrFail($parentId);

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

            $cursor = $ancestors instanceof Collection
                ? ($ancestors->has($cursor) ? $ancestors->get($cursor) : null)
                : ($ancestors[$cursor] ?? null);
        }
    }
}
