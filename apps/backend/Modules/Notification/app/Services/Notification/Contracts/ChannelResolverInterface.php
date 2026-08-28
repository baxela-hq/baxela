<?php

namespace Modules\Notification\Services\Notification\Contracts;

interface ChannelResolverInterface
{
    /**
     * @return string[] // ['sms', 'email', 'telegram', ...]
     */
    public function resolve(NotificationContext $context): array;
}
