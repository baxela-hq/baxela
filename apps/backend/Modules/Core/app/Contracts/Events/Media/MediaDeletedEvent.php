<?php

namespace Modules\Core\Contracts\Events\Media;

use Modules\Core\Contracts\Events\AbstractBaseEvent;

class MediaDeletedEvent extends AbstractBaseEvent
{
    public int $id;

    public int $user_id;

    public ?int $folder_id;

    public ?string $disk;

    public string $path;

    public string $filename;

    public ?string $extension;

    public string $mime_type;

    public string $size;

    public ?string $metadata;

    public string $created_at;
}
