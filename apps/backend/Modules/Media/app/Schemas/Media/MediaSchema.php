<?php

namespace Modules\Media\Schemas\Media;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;
use Modules\Media\Schemas\Module;

class MediaSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'media';

    public const string USER_ID = 'user_id';

    public const string FOLDER_ID = 'folder_id';

    public const string DISK = 'disk';

    public const string PATH = 'path';

    public const string NAME = 'name';

    public const string FILENAME = 'filename';

    public const string EXTENSION = 'extension';

    public const string MIME_TYPE = 'mime_type';

    public const string SIZE = 'size';

    public const string METADATA = 'metadata';

    public const string RES_URL = 'url';

    public const string REQ_FILE = 'file';
}
