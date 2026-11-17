<?php

namespace Modules\Notification\Services\Notification\Contracts;

use Modules\Notification\Services\Notification\DTOs\NotificationContext;

interface ChannelResolverInterface
{
    /**
     * @return string[] // ['sms', 'email', 'telegram', ...]
     */
    public function resolve(NotificationContext $context): array;
}
