<?php

namespace Modules\Catalog\Schemas\ProductImport;

use Modules\Catalog\Schemas\Module;
use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class ProductImportSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = Module::DB_PREFIX.'imports';

    public const string USER_ID = 'user_id';

    public const string MEDIA_ID = 'media_id';

    public const string FILENAME = 'filename';

    public const string STATUS = 'status';

    public const string STRATEGY = 'strategy';

    public const string DRY_RUN = 'dry_run';

    public const string TOTAL_ROWS = 'total_rows';

    public const string CREATED_COUNT = 'created_count';

    public const string UPDATED_COUNT = 'updated_count';

    public const string SKIPPED_COUNT = 'skipped_count';

    public const string FAILED_COUNT = 'failed_count';

    public const string DURATION_MS = 'duration_ms';

    public const string ERRORS = 'errors';

    public const string RES_USER = 'user';

    public const string REQ_MEDIA_ID = 'media_id';

    public const string REQ_MAPPING = 'mapping';

    public const string REQ_ON_DUPLICATE = 'on_duplicate';

    public const string REQ_DRY_RUN = 'dry_run';
}
