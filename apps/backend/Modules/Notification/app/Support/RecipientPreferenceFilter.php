<?php

namespace Modules\Notification\Support;

use Modules\Core\Contracts\Gateways\Auth\AccessGatewayInterface;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Schemas\Notification\NotificationChannelEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Services\Notification\DTOs\NotificationMessage;

/**
 * Drops recipients who turned the notification code off for a channel,
 * before builders materialize channel messages. Id-based channels
 * (database, webpush, telegram) filter directly; email recipients are
 * addresses, so only those that resolve to an app user via the access
 * gateway are filtered — env-configured admin addresses belong to no
 * user and always stay. Locked (transactional) codes skip filtering
 * entirely.
 */
class RecipientPreferenceFilter
{
    public function __construct(private readonly AccessGatewayInterface $accessGateway) {}

    public function filter(NotificationMessage $message): NotificationMessage
    {
        $code = NotificationCodeEnum::tryFrom($message->code);

        if ($code === null || LockedNotificationCodes::contains($code)) {
            return $message;
        }

        $recipients = $message->recipients;

        // Webpush and telegram ride on the database recipient list unless
        // a listener names their own — materialize the fallback here so
        // per-channel mutes apply to it and the dispatcher's empty-skip
        // sees one resolved map (mirrors the builders' own fallback).
        foreach ([NotificationChannelEnum::WEBPUSH, NotificationChannelEnum::TELEGRAM] as $channel) {
            if (! array_key_exists($channel->value, $recipients)) {
                $recipients[$channel->value] = $recipients[NotificationChannelEnum::DATABASE->value] ?? [];
            }
        }

        $message = $message->withRecipients($recipients);

        $emailToUserId = $this->emailUserIds($recipients);

        $userIds = array_unique([
            ...array_map(intval(...), $recipients[NotificationChannelEnum::DATABASE->value] ?? []),
            ...array_map(intval(...), $recipients[NotificationChannelEnum::WEBPUSH->value] ?? []),
            ...array_map(intval(...), $recipients[NotificationChannelEnum::TELEGRAM->value] ?? []),
            ...array_map(intval(...), array_values($emailToUserId)),
        ]);

        if ($userIds === []) {
            return $message;
        }

        $disabled = NotificationPreference::query()
            ->where(NotificationPreferenceSchema::AUDIENCE, $message->audience)
            ->where(NotificationPreferenceSchema::CODE, $code->value)
            ->where(NotificationPreferenceSchema::IS_ENABLED, false)
            ->whereIn(NotificationPreferenceSchema::USER_ID, $userIds)
            ->get()
            ->mapWithKeys(fn (NotificationPreference $row): array => [
                $row->{NotificationPreferenceSchema::USER_ID}.'|'.$row->{NotificationPreferenceSchema::CHANNEL}->value => true,
            ]);

        if ($disabled->isEmpty()) {
            return $message;
        }

        foreach ([NotificationChannelEnum::DATABASE, NotificationChannelEnum::WEBPUSH, NotificationChannelEnum::TELEGRAM] as $channel) {
            $name = $channel->value;

            $recipients[$name] = array_values(array_filter(
                $recipients[$name] ?? [],
                fn (int|string $id): bool => ! ($disabled[intval($id).'|'.$name] ?? false)
            ));
        }

        $recipients[NotificationChannelEnum::EMAIL->value] = array_values(array_filter(
            $recipients[NotificationChannelEnum::EMAIL->value] ?? [],
            fn (string $email): bool => ! ($disabled[(($emailToUserId[$email] ?? 0).'|'.NotificationChannelEnum::EMAIL->value)] ?? false)
        ));

        return $message->withRecipients($recipients);
    }

    /**
     * Email addresses that belong to app users, mapped to their user ids.
     *
     * @param  array<string, mixed>  $recipients
     * @return array<string, int>
     */
    private function emailUserIds(array $recipients): array
    {
        $emails = array_values(array_filter(
            $recipients[NotificationChannelEnum::EMAIL->value] ?? [],
            'is_string'
        ));

        return $emails === [] ? [] : array_map(intval(...), $this->accessGateway->getUserIdsByEmails($emails));
    }
}
