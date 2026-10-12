<?php

namespace Modules\Notification\Services\Notification\Builders;

use Modules\Notification\Services\Notification\Contracts\ChannelPayloadBuilderInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\TelegramMessage;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class TelegramPayloadBuilder implements ChannelPayloadBuilderInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
    ) {}

    /**
     * Telegram rides on the database notification templates: unless a
     * listener sets dedicated `telegram` variables/recipients, the
     * `database` ones are reused, so enabling the channel is a
     * config-only change.
     */
    public function build(NotificationMessage $message): TelegramMessage
    {
        $locale = $message->locale ?? app()->getLocale();

        $rendered = $this->templates->get('locale')->render(
            $message->data['telegram'] ?? $message->data['database'] ?? [],
            $locale,
            $message->code,
            $message->audience
        );

        return new TelegramMessage(
            code: $message->code,
            audience: $message->audience,
            title: $rendered->subject,
            body: $rendered->body,
            recipients: $message->recipients['telegram'] ?? $message->recipients['database'] ?? [],
            meta: $message->meta,
            locale: $locale,
        );
    }
}
