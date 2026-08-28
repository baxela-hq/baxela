<?php

namespace Modules\Notification\Services\Notification\DTOs;

class NotificationContext
{
    public function __construct(
        public readonly string $eventName,
        public readonly string $templateName,
        public readonly array $variables,
        public readonly string $locale,
        public readonly array $recipients // array of email addresses, phone numbers, etc.
    ) {}
}
