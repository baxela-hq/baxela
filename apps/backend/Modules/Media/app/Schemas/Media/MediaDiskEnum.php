<?php

namespace Modules\Media\Schemas\Media;

enum MediaDiskEnum: string
{
    case LOCAL = 'local';
    case PUBLIC = 'public';
    case S3 = 's3';
}
