<?php

namespace Modules\Media\Gateways;

use Exception;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaInput;
use Modules\Core\Contracts\Gateways\Media\DTOs\CreateMediaOutput;
use Modules\Core\Contracts\Gateways\Media\MediaGatewayInterface;
use Modules\Media\Actions\Admin\Media\CreateMediaAction;
use Modules\Media\Actions\Admin\Media\DeleteMediaAction;
use Modules\Media\DTOs\Admin\CreateMediaInput as CreateMediaInputDto;
use Modules\Media\Models\Media;
use Modules\Media\Schemas\Media\MediaDiskEnum;
use Modules\Media\Schemas\Media\MediaMimeTypeEnum;
use Modules\Media\Schemas\Media\MediaSchema;

class MediaGateway implements MediaGatewayInterface
{
    /**
     * @throws Exception
     */
    public function create(CreateMediaInput $input): ?CreateMediaOutput
    {
        $action = app(CreateMediaAction::class);
        $dto = CreateMediaInputDto::fill($input->toArray());

        $result = $action->handle($dto);

        if (! $result) {
            return null;
        }

        return CreateMediaOutput::fill($result->toArray());
    }

    /**
     * @throws Exception
     */
    public function delete(string $id): bool
    {
        $action = app(DeleteMediaAction::class);

        return $action->handle($id);
    }

    public function upsertLocal(string $sourcePath, string $targetPath): ?CreateMediaOutput
    {
        $disk = MediaDiskEnum::PUBLIC->value;
        $filename = basename($targetPath);

        Storage::disk($disk)->put($targetPath, file_get_contents($sourcePath));

        // Ownership mirrors the Media module's own seeder (user 1). Keying
        // the upsert on the path keeps the media id stable across re-seeds
        // while the stored file's metadata is refreshed. Mime types outside
        // the stored set resolve to null and fail the insert loudly.
        $media = Media::query()->updateOrCreate(
            [MediaSchema::PATH => $targetPath],
            [
                MediaSchema::USER_ID => 1,
                MediaSchema::FOLDER_ID => null,
                MediaSchema::DISK => $disk,
                MediaSchema::NAME => pathinfo($filename, PATHINFO_FILENAME),
                MediaSchema::FILENAME => $filename,
                MediaSchema::EXTENSION => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                MediaSchema::MIME_TYPE => MediaMimeTypeEnum::tryFrom(mime_content_type($sourcePath) ?: '')?->value,
                MediaSchema::SIZE => filesize($sourcePath),
            ],
        );

        return CreateMediaOutput::fill([
            'id' => (string) $media->getKey(),
            'url' => Storage::disk($disk)->url($targetPath),
            'filename' => $filename,
            'size' => filesize($sourcePath),
        ]);
    }
}
