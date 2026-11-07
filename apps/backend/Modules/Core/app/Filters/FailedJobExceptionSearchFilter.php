<?php

namespace Modules\Core\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\FailedJob;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Substring search over the failed_jobs.exception column.
 *
 * Short needles are rejected with a validation error instead of scanned:
 * the column is unindexed longText, and `%LIKE%` stays cheap only while
 * the table is pruned (gap-B3 D2/D7).
 */
/**
 * @implements Filter<FailedJob>
 */
class FailedJobExceptionSearchFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $needle = trim((string) $value);

        $min = (int) config('core.queue.failed_jobs.exception_search_min_length', FailedJobSchema::MIN_SEARCH_LENGTH);

        if (mb_strlen($needle) < $min) {
            throw ValidationException::withMessages([
                FailedJobSchema::FILTER_EXCEPTION_SEARCH => [
                    __('core::errors.failed_jobs.search_too_short', ['min' => $min]),
                ],
            ]);
        }

        $query->where(FailedJobSchema::TABLE.'.'.FailedJobSchema::EXCEPTION, 'like', "%{$needle}%");
    }
}
