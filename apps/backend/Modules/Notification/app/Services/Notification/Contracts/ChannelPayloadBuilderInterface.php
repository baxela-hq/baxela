<?php

namespace Modules\Notification\Services\Notification\Contracts;

use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

interface ChannelPayloadBuilderInterface
{
    public function build(NotificationMessage $message): MessageInterface;
}
