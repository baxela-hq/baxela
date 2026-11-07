<?php

namespace Modules\Core\Schemas\TransferImport;

use Modules\Core\Schemas\Shared\PkAndTimestampsTrait;

class TransferImportSchema
{
    use PkAndTimestampsTrait;

    public const string TABLE = 'transfer_imports';

    public const string USER_ID = 'user_id';

    public const string MODULE = 'module';

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

    public const string SUMMARY = 'summary';

    public const string REQ_FILE = 'file';

    public const string REQ_ON_DUPLICATE = 'on_duplicate';

    public const string REQ_DRY_RUN = 'dry_run';
}
