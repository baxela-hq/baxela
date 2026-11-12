<?php

namespace Modules\Setting\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Setting\Models\Setting;
use Modules\Setting\Schemas\Setting\SettingNameEnum;
use Modules\Setting\Schemas\Setting\SettingSchema;
use Throwable;

/**
 * FX auto-sync settings + machine-managed sync status (Part E). The
 * Core-side sync engine consumes this through the contract — the
 * Setting model stays inside this module.
 *
 * Credential handling (reviews F8/F12/F17/F21): the stored key is
 * encrypted at rest by UpdateSettingAction; decryption failure (an
 * APP_KEY rotation signature) surfaces as KEY_STATE_UNDECRYPTABLE with
 * an empty key — the sync then refuses with missing_api_key instead of
 * silently treating the credential as absent-by-configuration.
 */
class ResolvesFxSettings implements FxSettingsResolverInterface
{
    public const string CACHE_KEY = 'setting.fx';

    public function settings(): array
    {
        try {
            $values = Cache::remember(self::CACHE_KEY, now()->addMinutes(15), function (): array {
                return Setting::query()
                    ->whereIn(SettingSchema::NAME, [
                        SettingNameEnum::FX_AUTO_SYNC_ENABLED,
                        SettingNameEnum::FX_PROVIDER,
                        SettingNameEnum::FX_API_KEY,
                        SettingNameEnum::FX_SYNC_INTERVAL_HOURS,
                    ])
                    ->pluck(SettingSchema::VALUE, SettingSchema::NAME)
                    ->all();
            });
        } catch (Throwable) {
            // settings table not migrated yet (installer flows)
            $values = [];
        }

        [$apiKey, $keyState] = $this->resolveApiKey($values[SettingNameEnum::FX_API_KEY->value] ?? '');

        return [
            'enabled' => ($values[SettingNameEnum::FX_AUTO_SYNC_ENABLED->value] ?? '0') === '1',
            'provider' => $values[SettingNameEnum::FX_PROVIDER->value] ?? 'openexchangerates',
            'api_key' => $apiKey,
            'api_key_state' => $keyState,
            'interval_hours' => max(1, (int) ($values[SettingNameEnum::FX_SYNC_INTERVAL_HOURS->value] ?? 24)),
        ];
    }

    public function lastSyncStatus(): ?array
    {
        try {
            $raw = Setting::query()
                ->where(SettingSchema::NAME, SettingNameEnum::FX_SYNC_STATUS->value)
                ->value(SettingSchema::VALUE);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $status = json_decode($raw, true);

        return is_array($status) ? $status : null;
    }

    public function recordSyncStatus(array $status): void
    {
        Setting::query()
            ->where(SettingSchema::NAME, SettingNameEnum::FX_SYNC_STATUS->value)
            ->update([SettingSchema::VALUE => json_encode($status)]);

        // The status rides in the same cached rows as the settings.
        Cache::forget(self::CACHE_KEY);
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
        if (is_string($stored) && $stored !== '') {
            try {
                return [Crypt::decryptString($stored), self::KEY_STATE_SETTING];
            } catch (Throwable) {
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
