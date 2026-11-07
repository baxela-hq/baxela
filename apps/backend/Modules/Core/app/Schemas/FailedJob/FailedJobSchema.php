<?php

namespace Modules\Core\Schemas\FailedJob;

/**
 * Schema constants for Laravel's framework-managed `failed_jobs` table.
 *
 * Deliberately NOT prefixed with the module's DB_PREFIX: the table belongs to
 * the queue framework, not to a Baxela module (gap-B3 D1). No migration owns it
 * here either — the framework migration creates it.
 */
class FailedJobSchema
{
    public const string TABLE = 'failed_jobs';

    public const string ID = 'id';

    public const string UUID = 'uuid';

    public const string CONNECTION = 'connection';

    public const string QUEUE = 'queue';

    public const string PAYLOAD = 'payload';

    public const string EXCEPTION = 'exception';

    public const string FAILED_AT = 'failed_at';

    /** Custom-filter name for the exception substring search — not a column. */
    public const string FILTER_EXCEPTION_SEARCH = 'exception_search';

    /** Serialization key for the job's display name extracted from the payload — not a column. */
    public const string RES_JOB = 'job';

    /** Exception substrings shorter than this are rejected instead of scanned (gap-B3 D2). */
    public const int MIN_SEARCH_LENGTH = 3;

    /** Server-side truncation length for the exception excerpt on list rows (gap-B3 D2). */
    public const int EXCERPT_LENGTH = 300;
}
