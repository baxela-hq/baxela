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
     * Returns the media record (id + public url) or null when it could
     * not be registered.
     */
    public function upsertLocal(string $sourcePath, string $targetPath): ?CreateMediaOutput;
}
