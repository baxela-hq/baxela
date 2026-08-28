<?php

namespace Modules\Notification\Services\Notification\DTOs;

class RenderedTemplate
{
    public function __construct(
        public readonly ?string $subject,
        public readonly string $body
    ) {}
}
