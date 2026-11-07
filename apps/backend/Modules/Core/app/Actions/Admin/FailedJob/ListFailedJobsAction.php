<?php

namespace Modules\Core\Actions\Admin\FailedJob;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Filters\FailedJobExceptionSearchFilter;
use Modules\Core\Models\FailedJob;
use Modules\Core\Schemas\FailedJob\FailedJobSchema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListFailedJobsAction
{
    /**
     * @return LengthAwarePaginator<int, FailedJob>
     */
    public function handle(): LengthAwarePaginator
    {
        return QueryBuilder::for(FailedJob::class)
            ->allowedFilters(
                AllowedFilter::exact(FailedJobSchema::UUID),
                AllowedFilter::exact(FailedJobSchema::QUEUE),
                AllowedFilter::custom(FailedJobSchema::FILTER_EXCEPTION_SEARCH, new FailedJobExceptionSearchFilter),
            )
            ->allowedSorts(
                FailedJobSchema::ID,
                FailedJobSchema::FAILED_AT,
            )
            ->orderBy(FailedJobSchema::TABLE.'.'.FailedJobSchema::FAILED_AT, 'desc')
            ->orderBy(FailedJobSchema::TABLE.'.'.FailedJobSchema::ID, 'desc')
            ->paginate(Pagination::perPage());
    }
}
