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

    // Legacy mime some browsers historically send for .csv uploads; the
    // extension is re-verified by the CSV importer before parsing.
    case TEXT_CSV = 'text/csv';
    case APPLICATION_CSV_LEGACY = 'application/vnd.ms-excel';

    // Module data transfer files (e.g. the catalog JSON export).
    case APPLICATION_JSON = 'application/json';
}
