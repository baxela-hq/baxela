<?php

namespace Modules\Notification\Services\Notification\Contracts;

use Modules\Notification\Services\Notification\DTOs\SendResult;

interface NotificationChannelInterface
{
    public function send(MessageInterface $message): SendResult;
}
