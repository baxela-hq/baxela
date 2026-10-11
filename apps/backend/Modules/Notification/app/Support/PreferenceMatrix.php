<?php

namespace Modules\Notification\Support;

use Modules\Notification\Models\NotificationPreference;
use Modules\Notification\Schemas\Notification\NotificationAudienceEnum;
use Modules\Notification\Schemas\Notification\NotificationCodeEnum;
use Modules\Notification\Schemas\Notification\NotificationPreferenceSchema;

/**
 * Merges the config channel matrix (the default "who gets what") with a
 * user's sparse preference overrides into the entry shape the settings
 * endpoints and UI consume.
 */
class PreferenceMatrix
{
    /**
     * Codes configured for the audience with their default channels,
     * keyed by code in config order (grouped by domain).
     *
     * @return array<string, array<int, string>>
     */
    public static function configured(NotificationAudienceEnum $audience): array
    {
        $matrix = (array) config('notification.notifications.notifications', []);
        $result = [];

        foreach ($matrix as $module => $entities) {
            foreach ((array) $entities as $entity => $actions) {
                foreach ((array) $actions as $action => $audiences) {
                    foreach ((array) $audiences as $name => $channels) {
                        if ($name !== $audience->value) {
                            continue;
                        }

                        $code = NotificationCodeEnum::tryFrom("{$module}.{$entity}.{$action}");

                        if ($code !== null) {
                            $result[$code->value] = array_values(array_map(strval(...), (array) $channels));
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * All configurable entries for the audience, merged with the user's
     * override rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function entries(NotificationAudienceEnum $audience, int|string $userId): array
    {
        $configured = self::configured($audience);
        $overrides = self::overrides($audience, $userId, array_keys($configured));

        return array_map(
            fn (string $code, array $channels): array => self::entry($code, $channels, $overrides),
            array_keys($configured),
            $configured
        );
    }

    /**
     * The merged entry for a single code; null when it is not configured
     * for the audience.
     *
     * @return array<string, mixed>|null
     */
    public static function entryFor(NotificationAudienceEnum $audience, int|string $userId, NotificationCodeEnum $code): ?array
    {
        $configured = self::configured($audience);
        $channels = $configured[$code->value] ?? null;

        if ($channels === null) {
            return null;
        }

        return self::entry($code->value, $channels, self::overrides($audience, $userId, [$code->value]));
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, NotificationPreference> keyed "code|channel"
     */
    private static function overrides(NotificationAudienceEnum $audience, int|string $userId, array $codes): array
    {
        return NotificationPreference::query()
            ->where(NotificationPreferenceSchema::USER_ID, $userId)
            ->where(NotificationPreferenceSchema::AUDIENCE, $audience->value)
            ->whereIn(NotificationPreferenceSchema::CODE, $codes)
            ->get()
            ->keyBy(fn (NotificationPreference $row): string => self::overrideKey(
                $row->{NotificationPreferenceSchema::CODE}->value,
                $row->{NotificationPreferenceSchema::CHANNEL}->value
            ))
            ->all();
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, NotificationPreference>  $overrides
     * @return array<string, mixed>
     */
    private static function entry(string $code, array $channels, array $overrides): array
    {
        return [
            NotificationPreferenceSchema::CODE => $code,
            NotificationPreferenceSchema::RES_LOCKED => LockedNotificationCodes::contains(NotificationCodeEnum::from($code)),
            NotificationPreferenceSchema::RES_CHANNELS => array_map(
                fn (string $channel): array => [
                    NotificationPreferenceSchema::CHANNEL => $channel,
                    NotificationPreferenceSchema::RES_ENABLED => $overrides[self::overrideKey($code, $channel)]?->{NotificationPreferenceSchema::IS_ENABLED} ?? true,
                    NotificationPreferenceSchema::RES_IS_DEFAULT => ($overrides[self::overrideKey($code, $channel)] ?? null) === null,
                ],
                $channels
            ),
        ];
    }

    private static function overrideKey(string $code, string $channel): string
    {
        return $code.'|'.$channel;
    }
}
