<?php

namespace Modules\Setting\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Exceptions\PlatformSettingMissing;
use Modules\Core\Schemas\Shared\TenantColumn;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * Installation-level settings (§6.7 item 7 / P5). This is an indexed
 * read of the NULL-tenant `setting_settings` row — the partial unique
 * index on (name) WHERE tenant_id IS NULL is the lookup path. No central
 * mode, no store scope, no implicit default: a missing or malformed row
 * is reported as a 500, never served from another store.
 */
class PlatformSettings implements PlatformSettingsInterface
{
    private const int TTL_MINUTES = 15;

    /**
     * A single installation-level generation. Tenant-keyed aggregate caches
     * (setting.fx) carry this value so one platform write invalidates every
     * store's derived view without enumerating tenants.
     */
    public const string GENERATION_KEY = 'platform:setting.generation';

    /** @var list<string> */
    private const array NAMES = [
        self::FX_AUTO_SYNC_ENABLED,
        self::FX_PROVIDER,
        self::FX_API_KEY,
        self::FX_SYNC_INTERVAL_HOURS,
        self::FX_SYNC_STATUS,
        self::PLATFORM_IDENTITY,
    ];

    public static function cacheKey(string $name): string
    {
        return 'platform:setting.'.$name;
    }

    public static function isPlatformName(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }

    public static function generation(): int
    {
        return (int) (Cache::get(self::GENERATION_KEY) ?? 0);
    }

    public static function bumpGeneration(): void
    {
        if (! Cache::has(self::GENERATION_KEY)) {
            Cache::forever(self::GENERATION_KEY, 1);

            return;
        }

        Cache::increment(self::GENERATION_KEY);
    }

    public function get(string $name): mixed
    {
        if (! self::isPlatformName($name)) {
            throw new PlatformSettingMissing($name);
        }

        try {
            return Cache::remember(
                self::cacheKey($name),
                now()->addMinutes(self::TTL_MINUTES),
                fn (): mixed => $this->normalize($name, $this->read($name)),
            );
        } catch (PlatformSettingMissing $e) {
            report($e);

            throw $e;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            // A database outage is infrastructure, not a configuration
            // defect. Surface the original failure; callers with a
            // documented platform fallback may handle it explicitly.
            throw $e;
        }
    }

    public function has(string $name): bool
    {
        if (! self::isPlatformName($name)) {
            throw new PlatformSettingMissing($name);
        }

        return $this->rows()
            ->where(SettingSchema::NAME, $name)
            ->exists();
    }

    public function write(string $name, string $value): void
    {
        if (! self::isPlatformName($name)) {
            throw new PlatformSettingMissing($name);
        }

        $this->rows()->updateOrInsert(
            [SettingSchema::NAME => $name],
            [SettingSchema::VALUE => $value, SettingSchema::UPDATED_AT => now()],
        );

        $this->forget($name);
    }

    /** Drop one platform value from the cache. */
    public function forget(string $name): void
    {
        if (! self::isPlatformName($name)) {
            throw new PlatformSettingMissing($name);
        }

        Cache::forget(self::cacheKey($name));
        self::bumpGeneration();
    }

    /** Drop every allowlisted platform value from the cache. */
    public function invalidate(): void
    {
        foreach (self::NAMES as $name) {
            Cache::forget(self::cacheKey($name));
        }

        self::bumpGeneration();
    }

    /**
     * Platform rows are indexed by the partial unique index on (name)
     * WHERE tenant_id IS NULL. Going through the model without scopes keeps
     * these reads out of the raw query-site allowlist entirely.
     *
     * @return Builder<Setting>
     */
    protected function rows(): Builder
    {
        return Setting::withoutGlobalScopes()
            ->whereNull(TenantColumn::TENANT_ID);
    }

    protected function read(string $name): mixed
    {
        return $this->rows()
            ->where(SettingSchema::NAME, $name)
            ->value(SettingSchema::VALUE);
    }

    /** Reject anything that cannot be interpreted unambiguously. */
    protected function normalize(string $name, mixed $value): mixed
    {
        if ($value === null) {
            throw new PlatformSettingMissing($name);
        }

        return match ($name) {
            self::FX_AUTO_SYNC_ENABLED => $this->toggle($name, $value),
            self::FX_PROVIDER => $this->nonEmptyString($name, $value),
            self::FX_API_KEY => $this->stringValue($name, $value),
            self::FX_SYNC_INTERVAL_HOURS => $this->interval($name, $value),
            self::FX_SYNC_STATUS => $this->jsonValue($name, $value),
            self::PLATFORM_IDENTITY => $this->identity($name, $value),
            default => throw new PlatformSettingMissing($name),
        };
    }

    protected function toggle(string $name, mixed $value): bool
    {
        return match ($value) {
            '1' => true,
            '0' => false,
            default => throw new PlatformSettingMissing($name),
        };
    }

    protected function nonEmptyString(string $name, mixed $value): string
    {
        $string = $this->stringValue($name, $value);

        return $string === '' ? throw new PlatformSettingMissing($name) : $string;
    }

    protected function stringValue(string $name, mixed $value): string
    {
        return is_string($value) ? $value : throw new PlatformSettingMissing($name);
    }

    protected function interval(string $name, mixed $value): int
    {
        if (! is_numeric($value) || (int) $value < 1 || (int) $value > 8760) {
            throw new PlatformSettingMissing($name);
        }

        return (int) $value;
    }

    /**
     * An empty row means "never synced"; anything else must be a JSON object.
     *
     * @return array<string, mixed>|null
     */
    protected function jsonValue(string $name, mixed $value): ?array
    {
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($this->stringValue($name, $value), true);

        return is_array($decoded) ? $decoded : throw new PlatformSettingMissing($name);
    }

    /** @return array{name: string, url: string} */
    protected function identity(string $name, mixed $value): array
    {
        $identity = $this->jsonValue($name, $value);

        if (
            $identity === null
            || ! isset($identity['name'], $identity['url'])
            || ! is_string($identity['name']) || $identity['name'] === ''
            || ! is_string($identity['url']) || $identity['url'] === ''
        ) {
            throw new PlatformSettingMissing($name);
        }

        return ['name' => $identity['name'], 'url' => $identity['url']];
    }
}
