<?php

namespace Modules\Notification\Services\Notification\DTOs\Messages;

use Modules\Core\DTOs\Trait\ToArrayTrait;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;

class WebPushMessage implements MessageInterface
{
    use ToArrayTrait;

    public function __construct(
        public string $code,
        public string $audience,
        public string $title,
        public string $body,
        public array $recipients,
        public array $meta = [],
        /** UI locale the template rendered in; carried to the service worker. */
        public ?string $locale = null,
        /** Text direction hint for the OS-level notification. */
        public string $dir = 'ltr',
    ) {}
}
