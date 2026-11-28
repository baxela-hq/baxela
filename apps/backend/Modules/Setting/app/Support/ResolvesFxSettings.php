<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Contracts\Gateways\Setting\PlatformSettingsInterface;
use Modules\Core\Exceptions\PlatformSettingMissing;
use Modules\Core\Support\Tenancy\TenancyExceptions;
use Modules\Core\Support\Tenancy\TenantContext;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Throwable;

/**
 * FX auto-sync settings + machine-managed sync status (Part E). The
 * Core-side sync engine consumes this through the contract — the Setting
 * model stays inside this module.
 *
 * All configuration and status comes from the allowlisted NULL-tenant
 * rows through PlatformSettingsInterface (§6.7 item 7); tenant-facing
 * code never enters central mode and never reads a store's Setting row.
 * The cached view stays tenant-keyed because §6.7 item 6 lists it among
 * the store-dependent cache families.
 *
 * Credential handling (reviews F8/F12/F17/F21): the stored key is
 * encrypted at rest by the platform write path; decryption failure (an
 * APP_KEY rotation signature) surfaces as KEY_STATE_UNDECRYPTABLE with
 * an empty key — the sync then refuses with missing_api_key instead of
 * silently treating the credential as absent-by-configuration.
 */
class ResolvesFxSettings implements FxSettingsResolverInterface
{
    public const string CACHE_KEY = 'setting.fx';

    public function __construct(private readonly PlatformSettingsInterface $platformSettings) {}

    public static function cacheKey(): string
    {
        return app(TenantContext::class)->cachePrefix().self::CACHE_KEY;
    }

    public function settings(): array
    {
        $key = self::cacheKey();
        $generation = PlatformSettings::generation();
        $cached = Cache::get($key);

        if (
            is_array($cached)
            && ($cached['platform_generation'] ?? null) === $generation
            && is_array($cached['values'] ?? null)
        ) {
            /** @var array<string, mixed> $values */
            $values = $cached['values'];
        } else {
            // The value is a platform default, but the aggregate cache is
            // tenant-keyed (§6.7 item 6). Carrying the platform generation
            // lets one write invalidate every store's derived view.
            try {
                $values = [
                    SettingNameEnum::FX_AUTO_SYNC_ENABLED->value => $this->read(
                        PlatformSettingsInterface::FX_AUTO_SYNC_ENABLED,
                        false,
                    ),
                    SettingNameEnum::FX_PROVIDER->value => $this->read(
                        PlatformSettingsInterface::FX_PROVIDER,
                        'openexchangerates',
                    ),
                    SettingNameEnum::FX_API_KEY->value => $this->read(
                        PlatformSettingsInterface::FX_API_KEY,
                        '',
                    ),
                    SettingNameEnum::FX_SYNC_INTERVAL_HOURS->value => $this->read(
                        PlatformSettingsInterface::FX_SYNC_INTERVAL_HOURS,
                        24,
                    ),
                ];
            } catch (PlatformSettingMissing $e) {
                throw $e;
            } catch (Throwable $e) {
                if (TenancyExceptions::isTenancy($e)) {
                    throw $e;
                }

                // Only an infrastructure outage reaches this fallback.
                $values = [];
            }

            Cache::put($key, [
                'platform_generation' => $generation,
                'values' => $values,
            ], now()->addMinutes(15));
        }

        [$apiKey, $keyState] = $this->resolveApiKey(
            is_string($values[SettingNameEnum::FX_API_KEY->value] ?? null)
                ? $values[SettingNameEnum::FX_API_KEY->value]
                : '',
        );

        return [
            'enabled' => (bool) ($values[SettingNameEnum::FX_AUTO_SYNC_ENABLED->value] ?? false),
            'provider' => (string) ($values[SettingNameEnum::FX_PROVIDER->value] ?? 'openexchangerates'),
            'api_key' => $apiKey,
            'api_key_state' => $keyState,
            'interval_hours' => max(1, (int) ($values[SettingNameEnum::FX_SYNC_INTERVAL_HOURS->value] ?? 24)),
        ];
    }

    public function lastSyncStatus(): ?array
    {
        try {
            return $this->read(PlatformSettingsInterface::FX_SYNC_STATUS, null);
        } catch (PlatformSettingMissing $e) {
            throw $e;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return null;
        }
    }

    public function recordSyncStatus(array $status): void
    {
        // Same write path as the row update: the next read (in any store
        // or on the platform host) sees this status, not a stale value.
        $this->platformSettings->write(
            PlatformSettingsInterface::FX_SYNC_STATUS,
            json_encode($status, JSON_THROW_ON_ERROR),
        );
        Cache::forget(self::cacheKey());
    }

    /**
     * Missing/malformed values are configuration defects and propagate;
     * only infrastructure failures use the documented platform fallback.
     */
    protected function read(string $name, mixed $fallback): mixed
    {
        try {
            return $this->platformSettings->get($name);
        } catch (PlatformSettingMissing $e) {
            throw $e;
        } catch (Throwable $e) {
            if (TenancyExceptions::isTenancy($e)) {
                throw $e;
            }

            return $fallback;
        }
    }

    /**
     * Resolve the effective credential + its state WITHOUT exposing the
     * value beyond the contract (review F21): stored-but-undecryptable
     * is a distinct state, never silently "not configured".
     *
     * @return array{0: string, 1: string} [api_key, api_key_state]
     */
    protected function resolveApiKey(string $stored): array
    {
        if ($stored !== '') {
            try {
                return [Crypt::decryptString($stored), self::KEY_STATE_SETTING];
            } catch (Throwable $e) {
                if (TenancyExceptions::isTenancy($e)) {
                    throw $e;
                }

                return ['', self::KEY_STATE_UNDECRYPTABLE];
            }
        }

        $fallback = (string) config('core.fx.fallback_api_key', '');

        if ($fallback !== '') {
            return [$fallback, self::KEY_STATE_ENV];
        }

        return ['', self::KEY_STATE_NONE];
    }
}
