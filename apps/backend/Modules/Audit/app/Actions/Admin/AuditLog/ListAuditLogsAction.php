<?php

namespace Modules\Audit\Actions\Admin\AuditLog;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Audit\Filters\CreatedFromFilter;
use Modules\Audit\Filters\CreatedToFilter;
use Modules\Audit\Schemas\AuditLog\AuditLogSchema;
use Modules\Core\Utils\Pagination;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListAuditLogsAction extends AbstractAuditLogAction
{
    public function handle(): LengthAwarePaginator
    {
        $id = AuditLogSchema::TABLE.'.'.AuditLogSchema::ID;

        return QueryBuilder::for($this->model::class)
            ->allowedFilters(
                AllowedFilter::exact(AuditLogSchema::USER_ID),
                AllowedFilter::exact(AuditLogSchema::MODULE),
                AllowedFilter::exact(AuditLogSchema::ENTITY),
                AllowedFilter::exact(AuditLogSchema::ACTION),
                AllowedFilter::exact(AuditLogSchema::ENTITY_ID),
                AllowedFilter::custom(AuditLogSchema::FILTER_CREATED_FROM, new CreatedFromFilter),
                AllowedFilter::custom(AuditLogSchema::FILTER_CREATED_TO, new CreatedToFilter),
            )
            ->allowedSorts(
                AuditLogSchema::ID,
                AuditLogSchema::CREATED_AT,
            )
            ->with(AuditLogSchema::RES_USER)
            ->orderBy($id, 'desc')
            ->paginate(Pagination::perPage());
    }
}
