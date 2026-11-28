<?php

namespace Modules\Core\Contracts\Gateways\Setting;

use Modules\Core\Exceptions\PlatformSettingMissing;

/**
 * Installation-level setting values, read without ever entering central
 * mode (multi-tenancy §6.7 item 7). Implemented by the Setting module as
 * a direct indexed read of the NULL-tenant `setting_settings` rows.
 *
 * The allowlist is deliberately narrow: platform values are configuration
 * and identity, never a store's branding or a store-owned selection. The
 * platform base currency is reference data (`core_currencies.is_default`)
 * and does NOT belong here.
 *
 * A missing or malformed value throws PlatformSettingMissing — it must
 * never silently resolve to another store's value or to a default.
 */
interface PlatformSettingsInterface
{
    public const string FX_AUTO_SYNC_ENABLED = 'fx_auto_sync_enabled';

    public const string FX_PROVIDER = 'fx_provider';

    public const string FX_API_KEY = 'fx_api_key';

    public const string FX_SYNC_INTERVAL_HOURS = 'fx_sync_interval_hours';

    public const string FX_SYNC_STATUS = 'fx_sync_status';

    public const string PLATFORM_IDENTITY = 'platform_identity';

    /**
     * Normalized values: bool for the toggle, string for provider/key,
     * int for the interval, array|null for the JSON-backed status and
     * identity rows.
     *
     * @throws PlatformSettingMissing
     */
    public function get(string $name): mixed;

    /** Whether the indexed NULL-tenant row exists. */
    public function has(string $name): bool;

    /** Upsert a raw stored value without entering central mode. */
    public function write(string $name, string $value): void;

    /** Invalidate one cached platform value after its row was written. */
    public function forget(string $name): void;
}
