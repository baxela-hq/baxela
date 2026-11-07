<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;

/**
 * Eloquent representation of Laravel's framework-managed `failed_jobs` table
 * (gap-B3 D1): the queue framework owns the schema and lifecycle — this model
 * only backs the admin visibility surface (list / show / retry / forget).
 *
 * The table breaks the conventional model configuration, so everything is
 * explicit: no `updated_at`/`created_at` pair exists (the sole timestamp is
 * `failed_at`, set by the framework on insert), and the primary key is the
 * auto-increment `id` while all API addressing goes through `uuid`.
 */
class FailedJob extends Model
{
    protected $table = FailedJobSchema::TABLE;

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        FailedJobSchema::UUID,
        FailedJobSchema::CONNECTION,
        FailedJobSchema::QUEUE,
        FailedJobSchema::PAYLOAD,
        FailedJobSchema::EXCEPTION,
        FailedJobSchema::FAILED_AT,
    ];

    protected function casts(): array
    {
        return [
            // `failed_at` is written by the framework with useCurrent(); it is
            // not a Laravel-managed timestamp and must not be treated like one.
            FailedJobSchema::FAILED_AT => 'datetime',
        ];
    }
}
