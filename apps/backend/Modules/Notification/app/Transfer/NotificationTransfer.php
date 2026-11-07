<?php

namespace Modules\Notification\Transfer;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportOptions;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\ImportResult;
use Modules\Core\Contracts\Gateways\Transfer\DTOs\TransferSection;
use Modules\Core\Contracts\Gateways\Transfer\ModuleTransferInterface;
use Modules\Notification\Models\Notification;
use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Models\PushSubscription;
use Modules\Notification\Models\TelegramSubscription;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;
use Modules\Notification\Schemas\Notification\NotificationSchema;
use Modules\Notification\Schemas\PushSubscription\PushSubscriptionSchema;
use Modules\Notification\Schemas\TelegramSubscription\TelegramSubscriptionSchema;

/**
 * Notification module's implementation of the data-transfer standard:
 * export-only. Notifications are per-user history, preferences are
 * per-user and per-channel, and push/telegram subscriptions are bound
 * to device endpoints of the exporting store — replaying any of it into
 * another store would be meaningless, so no import surface is exposed.
 */
class NotificationTransfer implements ModuleTransferInterface
{
    public const string SECTION_NOTIFICATIONS = 'notifications';

    public const string SECTION_PREFERENCES = 'notification-preferences';

    public const string SECTION_PUSH_SUBSCRIPTIONS = 'push-subscriptions';

    public const string SECTION_TELEGRAM_SUBSCRIPTIONS = 'telegram-subscriptions';

    public function sections(): array
    {
        return [
            TransferSection::exportOnly(self::SECTION_NOTIFICATIONS),
            TransferSection::exportOnly(self::SECTION_PREFERENCES),
            TransferSection::exportOnly(self::SECTION_PUSH_SUBSCRIPTIONS),
            TransferSection::exportOnly(self::SECTION_TELEGRAM_SUBSCRIPTIONS),
        ];
    }

    public function exportRows(string $section): array
    {
        return match ($section) {
            self::SECTION_NOTIFICATIONS => $this->exportRowsOf(
                Notification::query()->orderBy(NotificationSchema::ID),
                fn ($record) => [
                    NotificationSchema::USER_ID => (int) $record->{NotificationSchema::USER_ID},
                    NotificationSchema::CODE => $record->{NotificationSchema::CODE}->value,
                    NotificationSchema::AUDIENCE => $record->{NotificationSchema::AUDIENCE}->value,
                    NotificationSchema::TITLE => $record->{NotificationSchema::TITLE},
                    NotificationSchema::BODY => $record->{NotificationSchema::BODY},
                    NotificationSchema::META => $record->{NotificationSchema::META},
                    NotificationSchema::READ_AT => $record->{NotificationSchema::READ_AT}?->toIso8601String(),
                    // created_at is not datetime-cast on this model (no
                    // updated_at twin) — normalize explicitly.
                    NotificationSchema::CREATED_AT => Carbon::parse(
                        $record->{NotificationSchema::CREATED_AT}
                    )?->toIso8601String(),
                ],
            ),
            self::SECTION_PREFERENCES => $this->exportRowsOf(
                NotificationPreference::query()->orderBy(NotificationPreferenceSchema::ID),
                fn ($record) => [
                    NotificationPreferenceSchema::USER_ID => (int) $record->{NotificationPreferenceSchema::USER_ID},
                    NotificationPreferenceSchema::CODE => $record->{NotificationPreferenceSchema::CODE}->value,
                    NotificationPreferenceSchema::AUDIENCE => $record->{NotificationPreferenceSchema::AUDIENCE}->value,
                    NotificationPreferenceSchema::CHANNEL => $record->{NotificationPreferenceSchema::CHANNEL}->value,
                    NotificationPreferenceSchema::IS_ENABLED => (bool) $record->{NotificationPreferenceSchema::IS_ENABLED},
                ],
            ),
            self::SECTION_PUSH_SUBSCRIPTIONS => $this->exportRowsOf(
                PushSubscription::query()->orderBy(PushSubscriptionSchema::ID),
                fn ($record) => [
                    PushSubscriptionSchema::USER_ID => (int) $record->{PushSubscriptionSchema::USER_ID},
                    PushSubscriptionSchema::ENDPOINT => $record->{PushSubscriptionSchema::ENDPOINT},
                    PushSubscriptionSchema::P256DH => $record->{PushSubscriptionSchema::P256DH},
                    PushSubscriptionSchema::AUTH => $record->{PushSubscriptionSchema::AUTH},
                    PushSubscriptionSchema::USER_AGENT => $record->{PushSubscriptionSchema::USER_AGENT},
                    PushSubscriptionSchema::LOCALE => $record->{PushSubscriptionSchema::LOCALE},
                ],
            ),
            self::SECTION_TELEGRAM_SUBSCRIPTIONS => $this->exportRowsOf(
                TelegramSubscription::query()->orderBy(TelegramSubscriptionSchema::ID),
                fn ($record) => [
                    TelegramSubscriptionSchema::USER_ID => (int) $record->{TelegramSubscriptionSchema::USER_ID},
                    TelegramSubscriptionSchema::CHAT_ID => (int) $record->{TelegramSubscriptionSchema::CHAT_ID},
                    TelegramSubscriptionSchema::USERNAME => $record->{TelegramSubscriptionSchema::USERNAME},
                    TelegramSubscriptionSchema::LINKED_AT => $record->{TelegramSubscriptionSchema::LINKED_AT}?->toIso8601String(),
                ],
            ),
            default => throw new InvalidArgumentException("Unknown section [{$section}]."),
        };
    }

    /**
     * Export-only: the Transfer engine skips these sections before it
     * ever reaches here.
     */
    public function importRows(string $section, array $rows, ImportOptions $options): ImportResult
    {
        throw new InvalidArgumentException("Section [{$section}] is export-only.");
    }

    /**
     * @param  Builder  $query
     * @return list<array{source_id: int, payload: array<string, mixed>}>
     */
    private function exportRowsOf($query, \Closure $payload): array
    {
        $rows = [];
        $query->chunkById(200, function ($records) use (&$rows, $payload): void {
            foreach ($records as $record) {
                $rows[] = [
                    self::KEY_SOURCE_ID => (int) $record->getKey(),
                    self::KEY_PAYLOAD => $payload($record),
                ];
            }
        });

        return $rows;
    }
}
