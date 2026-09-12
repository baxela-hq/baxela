<?php

namespace Modules\Core\Contracts\Gateways\Media;

use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaInput;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaOutput;

interface MediaGatewayInterface
{
    public function create(CreateMediaInput $input): ?CreateMediaOutput;

    public function delete(string $id): bool;

    /**
     * Register a file already on the local filesystem in the media
     * library, publishing it to the given path on the public disk.
     * Idempotent — keyed by the target path, so repeated runs keep the
     * media id stable while refreshing the stored file and its metadata.
     * The media row is filed under the given folder id when provided.
     * Returns the media record (id + public url) or null when it could
     * not be registered.
     */
    public function upsertLocal(string $sourcePath, string $targetPath, ?int $folderId = null): ?CreateMediaOutput;

    /**
     * Resolve a slash-separated folder path (e.g. 'Catalog/Product')
     * against the folder tree, segment by segment from the root. Returns
     * the id of the last segment, or null when any segment is missing.
     */
    public function getFolderIdByPath(string $path): ?int;
}
