<?php

namespace Modules\Notification\Services\Notification\Contracts;

use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

interface NotificationDispatcherInterface
{
    public function dispatch(NotificationMessage $request): void;
}
