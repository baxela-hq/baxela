<?php

namespace Modules\Notification\Services\Notification\Builders;

use Modules\Notification\Services\Notification\Contracts\ChannelPayloadBuilderInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\EmailMessage;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class EmailPayloadBuilder implements ChannelPayloadBuilderInterface
{
    public function __construct(private readonly TemplateRepositoryInterface $templates) {}

    public function build(NotificationMessage $message): EmailMessage
    {
        $rendered = $this->templates->get('blade')->render(
            $message->data['email'] ?? [],
            config('app.locale'),
            $message->code,
            $message->audience
        );

        return new EmailMessage(
            // Channel keys match the channel names (see DatabasePayloadBuilder);
            // this was 'emails' and silently dropped every recipient.
            recipients: $message->recipients['email'] ?? [],
            subject: $rendered->subject,
            body: $rendered->body,
            meta: $message->meta,
        );
    }
}
