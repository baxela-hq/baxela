<?php

namespace Modules\Notification\Services\Notification\DTOs;

class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null
    ) {}
}
