<?php

namespace Modules\Notification\Services\Notification;

use Modules\Notification\Services\Notification\Contracts\BuilderRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\ChannelRepositoryInterface;
use Modules\Notification\Services\Notification\Contracts\NotificationDispatcherInterface;
use Modules\Notification\Services\Notification\Contracts\TemplateRepositoryInterface;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;
use Modules\Notification\Support\RecipientPreferenceFilter;

class NotificationService implements NotificationDispatcherInterface
{
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private BuilderRepositoryInterface $builderRepository,
        private TemplateRepositoryInterface $templateRepository,
        private RecipientPreferenceFilter $recipientFilter,
    ) {}

    public function dispatch(NotificationMessage $message): void
    {
        $channels = $message->channel
            ?? config("notification.notifications.notifications.{$message->code}.{$message->audience}", []);

        $message = $this->recipientFilter->filter($message);

        foreach ($channels as $channelName) {

            // No recipients left on a channel (e.g. everyone opted out via
            // preferences) — skip it instead of building a message nobody
            // would receive.
            if (($message->recipients[$channelName] ?? []) === []) {
                continue;
            }

            $builder = $this->builderRepository->get($channelName);

            $channelMessage = $builder->build($message);

            $channel = $this->channelRepository->get($channelName);

            $channel->send($channelMessage);
        }

        //        $templateRepository = $this->templateRepository->get($message->templateEngine);
        //
        //        $rendered = $templateRepository->render(
        //            $message->variables,
        //            config('app.locale'),
        //            $message->code,
        //            $message->audience
        //        );
        //
        //        $message->subject = $rendered->subject;
        //        $message->content = $rendered->body;
        //
        //        $channelRepository = $this->channelRepository->get($message->channel);
        //        $channelRepository->send($message);
    }
}
