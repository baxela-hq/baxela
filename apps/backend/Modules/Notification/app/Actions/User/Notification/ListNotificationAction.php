<?php

namespace Modules\Notification\Actions\User\Notification;

use Illuminate\Pagination\AbstractPaginator;
use Modules\Core\Utils\Auth;
use Modules\Notification\Models\Notification;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListNotificationAction
{
    public function handle(): AbstractPaginator
    {
        return QueryBuilder::for(Notification::class)
            ->where(NotificationSchema::USER_ID, Auth::id())
            ->allowedFilters(
                AllowedFilter::exact(NotificationSchema::CODE),
                AllowedFilter::callback('unread', fn ($query, $value) => filter_var($value, FILTER_VALIDATE_BOOL)
                    ? $query->whereNull(NotificationSchema::READ_AT)
                    : $query->whereNotNull(NotificationSchema::READ_AT)),
            )
            ->allowedSorts(NotificationSchema::ID, NotificationSchema::CREATED_AT)
            ->defaultSort('-'.NotificationSchema::ID)
            ->paginate(15);
    }
}
