<?php

namespace Modules\Notification\Services\Notification\DTOs;

use Modules\Core\DTOs\Trait\ToArrayTrait;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;

class NotificationMessage implements MessageInterface
{
    use ToArrayTrait;

    public function __construct(
        public readonly string $code,
        public readonly string $audience,
        public readonly array $recipients = [],
        public readonly array $data = [],
        public readonly ?array $channel = null,
        public ?string $subjectOverride = null, // Nullable for channels like SMS
        public ?string $bodyOverride = null,
        public readonly array $meta = [] // e.g., sender, reply_to for email
    ) {}
}
