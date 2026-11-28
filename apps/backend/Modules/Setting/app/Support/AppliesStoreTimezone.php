<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * Applies the store's timezone setting over Laravel's app.timezone and
 * the process-wide PHP default. The lookup is tenant-keyed — one store's
 * cached timezone is never served to another (§6.7 item 6; rows 10/64).
 */
class AppliesStoreTimezone
{
    public const string CACHE_KEY = 'setting.timezone';

    public static function cacheKey(): string
    {
        return app(TenantContext::class)->cachePrefix().self::CACHE_KEY;
    }

    public function __invoke(): void
    {
        try {
            $timezone = Cache::remember(
                self::cacheKey(),
                now()->addHour(),
                fn () => Setting::query()
                    ->where(SettingSchema::NAME, SettingNameEnum::TIMEZONE->value)
                    ->value(SettingSchema::VALUE)
            );
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            // settings table not migrated yet (fresh install, first migrate)
            return;
        }

        if (is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }
    }
}
