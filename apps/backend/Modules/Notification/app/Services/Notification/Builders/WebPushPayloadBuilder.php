<?php

namespace Modules\Notification\Services\Notification\Builders;

use Modules\Core\Contracts\Gateways\Core\CoreGatewayInterface;
use Modules\Notification\Services\Notification\Contracts\ChannelPayloadBuilderInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\WebPushMessage;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class WebPushPayloadBuilder implements ChannelPayloadBuilderInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templates,
        private readonly CoreGatewayInterface $coreGateway,
    ) {}

    /**
     * Web push rides on the database notification templates: unless a
     * listener sets dedicated `webpush` variables/recipients, the
     * `database` ones are reused, so enabling the channel is a
     * config-only change.
     */
    public function build(NotificationMessage $message): WebPushMessage
    {
        $locale = $message->locale ?? app()->getLocale();

        $rendered = $this->templates->get('locale')->render(
            $message->data['webpush'] ?? $message->data['database'] ?? [],
            $locale,
            $message->code,
            $message->audience
        );

        return new WebPushMessage(
            code: $message->code,
            audience: $message->audience,
            title: $rendered->subject,
            body: $rendered->body,
            recipients: $message->recipients['webpush'] ?? $message->recipients['database'] ?? [],
            meta: $message->meta,
            locale: $locale,
            dir: $this->directionFor($locale),
        );
    }

    /**
     * Direction comes from the language's is_rtl flag (Core owns the
     * language list); unknown locales fall back to LTR.
     */
    private function directionFor(string $locale): string
    {
        $language = $this->coreGateway
            ->getActiveLanguages()
            ->first(fn ($language) => $language->code === $locale);

        return ($language?->is_rtl ?? false) ? 'rtl' : 'ltr';
    }
}
