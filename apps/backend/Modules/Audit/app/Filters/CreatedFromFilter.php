<?php

namespace Modules\Audit\Filters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Audit\Models\AuditLog;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * @implements Filter<AuditLog>
 */
class CreatedFromFilter implements Filter
{
    /**
     * Inclusive lower bound on the audit log's created_at column.
     */
    /**
     * @param  Builder<AuditLog>  $query
     */
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $query->whereDate($query->getModel()->getCreatedAtColumn(), '>=', $value);
    }
}
