<?php

namespace Modules\Notification\Services\Notification\DTOs\Messages;

use Modules\Core\DTOs\Trait\ToArrayTrait;
use Modules\Notification\Services\Notification\Contracts\MessageInterface;

class WebPushMessage implements MessageInterface
{
    use ToArrayTrait;

    /**
     * @param  list<string|int>|array<string, list<int|string>>  $recipients
     * @param  array<string, mixed>  $meta
     */
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
        /** Store identity resolved at render time — never carried in the queued message. */
        public ?string $storeName = null,
        public ?string $storefrontUrl = null,
    ) {}
}
