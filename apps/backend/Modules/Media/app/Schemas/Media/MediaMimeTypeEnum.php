<?php

namespace Modules\Media\Schemas\Media;

use Modules\Core\Schemas\Shared\ToArrayTrait;

enum MediaMimeTypeEnum: string
{
    use ToArrayTrait;

    case IMAGE_JPEG = 'image/jpeg';
    case IMAGE_JPG = 'image/jpg';
    case IMAGE_PNG = 'image/png';
    case IMAGE_GIF = 'image/gif';
}
