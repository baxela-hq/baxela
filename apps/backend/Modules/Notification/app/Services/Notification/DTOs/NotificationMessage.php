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
        public readonly array $meta = [], // e.g., sender, reply_to for email
        /**
         * Language the templates render in; captured at event-dispatch time
         * so queued sends keep the recipient's language. Falls back to the
         * app locale when null.
         */
        public readonly ?string $locale = null,
    ) {}

    /**
     * Immutable copy with a replaced recipient map (per-channel lists),
     * e.g. after recipient filtering — everything else is carried over.
     */
    public function withRecipients(array $recipients): self
    {
        return new self(
            code: $this->code,
            audience: $this->audience,
            recipients: $recipients,
            data: $this->data,
            channel: $this->channel,
            subjectOverride: $this->subjectOverride,
            bodyOverride: $this->bodyOverride,
            meta: $this->meta,
            locale: $this->locale,
        );
    }
}
