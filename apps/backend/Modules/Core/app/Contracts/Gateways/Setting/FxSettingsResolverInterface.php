<?php

namespace Modules\Core\Contracts\Gateways\Setting;

/**
 * Read/write access to the FX auto-sync settings and machine-managed
 * sync status (Part E). Implemented in the Setting module — Core never
 * touches the Setting model directly.
 *
 * The credential NEVER leaves this contract as plaintext to consumers
 * other than the provider manager, and its STATE is always available
 * without its value: `api_key_state` distinguishes "admin-configured"
 * from "env fallback" from "not configured" from "stored but
 * undecryptable" (an APP_KEY rotation signature — review F21).
 */
interface FxSettingsResolverInterface
{
    public const string KEY_STATE_SETTING = 'setting';

    public const string KEY_STATE_ENV = 'env';

    public const string KEY_STATE_NONE = 'none';

    public const string KEY_STATE_UNDECRYPTABLE = 'undecryptable';

    /**
     * @return array{enabled: bool, provider: string, api_key: string, api_key_state: string, interval_hours: int}
     */
    public function settings(): array;

    /**
     * The machine-managed sync status (bounded, review F15) —
     * `last_attempt` + `last_success` halves, null before the first run.
     *
     * @return array<string, mixed>|null
     */
    public function lastSyncStatus(): ?array;

    /**
     * Persist the machine-managed sync status. Never called with
     * credential material; never overwrites the running operation's
     * status on a lock collision (transient outcome, review F19 note).
     *
     * @param  array<string, mixed>  $status
     */
    public function recordSyncStatus(array $status): void;
}
