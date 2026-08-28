<?php

namespace Modules\Notification\Services\Notification\Contracts;

interface ChannelRepositoryInterface
{
    public function get(string $name): NotificationChannelInterface;
}
