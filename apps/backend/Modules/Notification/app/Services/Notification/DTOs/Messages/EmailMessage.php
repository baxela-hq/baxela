<?php

namespace Modules\Notification\Services\Notification\DTOs\Messages;

use Modules\Core\DTOs\Trait\ToArrayTrait;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;

class EmailMessage implements MessageInterface
{
    use ToArrayTrait;

    public function __construct(
        public array $recipients,
        public string $subject,
        public string $body,
        public array $meta = [],
    ) {}
}
