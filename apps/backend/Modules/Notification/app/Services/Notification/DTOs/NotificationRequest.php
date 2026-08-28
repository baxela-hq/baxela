<?php

namespace Modules\Notification\Services\Notification\DTOs;

class NotificationRequest
{
    public function __construct(
        public readonly string $eventName,
        public readonly array $variables,
        public readonly string $locale,
        public readonly array $recipients
    ) {}
}
