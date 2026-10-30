<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * Applies the store's timezone setting over Laravel's app.timezone.
 *
 * Called at boot; a missing/garbage value falls back to the configured
 * default (config/app.php). The lookup is cached — UpdateSettingAction
 * flushes the key on every settings write.
 */
class AppliesStoreTimezone
{
    public const string CACHE_KEY = 'setting.timezone';

    public function __invoke(): void
    {
        try {
            $timezone = Cache::remember(
                self::CACHE_KEY,
                now()->addHour(),
                fn () => Setting::query()
                    ->where(SettingSchema::NAME, SettingNameEnum::TIMEZONE->value)
                    ->value(SettingSchema::VALUE)
            );
        } catch (Throwable) {
            // settings table not migrated yet (fresh install, first migrate)
            return;
        }

        if (is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
