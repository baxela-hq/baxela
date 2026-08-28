<?php

namespace Modules\Notification\Services\Notification\Builders;

use Modules\Notification\Services\Notification\Contracts\ChannelPayloadBuilderInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\DTOs\Messages\DatabaseMessage;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

class DatabasePayloadBuilder implements ChannelPayloadBuilderInterface
{
    public function __construct(private readonly TemplateRepositoryInterface $templates) {}

    public function build(NotificationMessage $message): DatabaseMessage
    {
        $rendered = $this->templates->get('locale')->render(
            $message->data['database'] ?? [],
            'database',
            $message->code,
            $message->audience
        );

        return new DatabaseMessage(
            code: $message->code,
            audience: $message->audience,
            title: $rendered->subject,
            body: $rendered->body,
            recipients: $message->recipients['database'] ?? [],
            meta: $message->meta,
        );
    }
}
