<?php

namespace Modules\Audit\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class CreatedToFilter implements Filter
{
    /**
     * Inclusive upper bound on the audit log's created_at column.
     */
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->whereDate($query->getModel()->getCreatedAtColumn(), '<=', $value);
    }
}
