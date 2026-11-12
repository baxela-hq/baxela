<?php

namespace Modules\Core\Actions\Admin\Currency;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Gateways\Fx\FxProviderException;
use Modules\Core\Contracts\Gateways\Fx\FxRateSnapshot;
use Modules\Core\Contracts\Gateways\Setting\FxSettingsResolverInterface;
use Modules\Core\Exceptions\BaseException;
use Modules\Core\Models\Currency;
use Modules\Core\Schemas\Currency\CurrencySchema;
use Modules\Core\Support\Fx\FxProviderManager;
use Modules\Core\Support\Fx\FxRateFormatter;
use Throwable;

/**
 * The FX sync engine (launch-readiness Part E). Two phases with a hard
 * boundary (review F3): everything that can fail — settings, provider,
 * snapshot validation, rate formatting — runs OUTSIDE any transaction;
 * phase 2 then applies all rate updates in ONE transaction under the
 * shared currency lock protocol (F18). A failure anywhere rolls back
 * with zero rate changes; `last_success` survives failed attempts.
 *
 * Standing invariants: a sync can never leave partially updated rates;
 * the API key never appears in exceptions, logs, or status output; no
 * float ever touches a rate value.
 */
class SyncExchangeRatesAction
{
    public const string LOCK_NAME = 'core.fx.sync';

    public const string OUTCOME_SYNCED = 'synced';

    public const string OUTCOME_SYNC_IN_PROGRESS = 'sync_in_progress';

    public const string OUTCOME_SKIPPED_DISABLED = 'skipped_disabled';

    public const string OUTCOME_SKIPPED_NOT_DUE = 'skipped_not_due';

    public const string OUTCOME_SKIPPED_COOLDOWN = 'skipped_cooldown';

    public const string ERROR_NO_DEFAULT = 'core.fx_no_default_currency';

    public const string ERROR_BASE_MISMATCH = 'core.base_mismatch';

    public const string ERROR_STALE = 'core.stale_rates';

    public const string ERROR_FUTURE = 'core.invalid_timestamp';

    public const string ERROR_MISSING_KEY = 'core.missing_api_key';

    public const string ERROR_SYNC_FAILED = 'core.sync_failed';

    /** Status detail arrays are capped (review F15). */
    public const int STATUS_DETAIL_CAP = 10;

    public function __construct(
        protected FxSettingsResolverInterface $fxSettings,
        protected FxProviderManager $providers,
    ) {}

    /**
     * Run one synchronization attempt.
     *
     * @param  bool  $force  bypass the enabled/due/cooldown gates (the
     *                       admin "Sync now" path); the lock and every
     *                       validation still apply
     * @return array{outcome: string, status: array<string, mixed>|null} outcome is one of
     *                                                                   the OUTCOME_* constants; `status` carries
     *                                                                   the fresh sync status on success
     *
     * @throws BaseException sanitized failure codes; failure status is
     *                       recorded before the throw
     * @throws FxProviderException provider failures, status recorded
     */
    public function sync(bool $force = false): array
    {
        $settings = $this->fxSettings->settings();

        if (! $force && ! $settings['enabled']) {
            return ['outcome' => self::OUTCOME_SKIPPED_DISABLED, 'status' => null];
        }

        $status = $this->fxSettings->lastSyncStatus();

        if (! $force && ! $this->isDue($settings, $status)) {
            return ['outcome' => $this->skipOutcome($status), 'status' => null];
        }

        if ($settings['api_key'] === '') {
            $this->recordFailure($status, self::ERROR_MISSING_KEY);

            throw new BaseException(self::ERROR_MISSING_KEY, 422);
        }

        $default = Currency::query()->where(CurrencySchema::IS_DEFAULT, true)->first();

        if ($default === null) {
            $this->recordFailure($status, self::ERROR_NO_DEFAULT);

            throw new BaseException(self::ERROR_NO_DEFAULT, 422);
        }

        // TTL derives from the HTTP timeout (review F14): the lock must
        // outlive fetch + processing.
        $ttl = max(
            (int) config('core.fx.lock.seconds', 60),
            4 * (int) config('core.fx.http.timeout', 15),
        );

        $lock = Cache::lock(self::LOCK_NAME, $ttl);

        if (! $lock->get()) {
            // Transient outcome — never writes status (F19 note).
            return ['outcome' => self::OUTCOME_SYNC_IN_PROGRESS, 'status' => null];
        }

        try {
            $snapshot = $this->providers
                ->for($settings['provider'], $settings['api_key'])
                ->fetchRates($default->{CurrencySchema::CODE});

            $this->guardSnapshot($snapshot, $default->{CurrencySchema::CODE});

            ['updates' => $updateSet, 'skipped' => $phaseSkips] = $this->buildUpdateSet($snapshot);

            $status = $this->applyRates($snapshot, $default->{CurrencySchema::CODE}, $updateSet, $phaseSkips);
        } catch (Throwable $e) {
            $this->recordFailure($status, $this->sanitizeErrorCode($e));

            throw $e;
        } finally {
            $lock->release();
        }

        return ['outcome' => self::OUTCOME_SYNCED, 'status' => $status];
    }

    /**
     * The three-gate due-check (review F19): enabled → retry cooldown
     * (only for failed last attempts — any other or unexpected status
     * NEVER suppresses syncing) → success interval.
     *
     * @param  array{enabled: bool, provider: string, api_key: string, api_key_state: string, interval_hours: int}  $settings
     * @param  array<string, mixed>|null  $status
     */
    protected function isDue(array $settings, ?array $status): bool
    {
        $now = now();

        $lastAttempt = is_array($status['last_attempt'] ?? null) ? $status['last_attempt'] : null;

        if (($lastAttempt['status'] ?? null) === 'failed') {
            $cooldown = (int) config('core.fx.retry.cooldown_minutes', 30);

            $failedAt = isset($lastAttempt['at']) ? Carbon::parse($lastAttempt['at']) : null;

            if ($failedAt !== null && $now->lt($failedAt->addMinutes($cooldown))) {
                return false; // within the retry cooldown
            }
        }

        $lastSuccess = is_array($status['last_success'] ?? null) ? $status['last_success'] : null;

        if ($lastSuccess === null) {
            return true; // never synced — due
        }

        $succeededAt = isset($lastSuccess['at']) ? Carbon::parse($lastSuccess['at']) : null;

        if ($succeededAt === null) {
            return true;
        }

        return $now->gte($succeededAt->addHours(max(1, $settings['interval_hours'])));
    }

    /**
     * Which skip outcome applies (called only when not due).
     *
     * @param  array<string, mixed>|null  $status
     */
    protected function skipOutcome(?array $status): string
    {
        $lastAttempt = is_array($status['last_attempt'] ?? null) ? $status['last_attempt'] : null;

        if (($lastAttempt['status'] ?? null) === 'failed') {
            return self::OUTCOME_SKIPPED_COOLDOWN;
        }

        return self::OUTCOME_SKIPPED_NOT_DUE;
    }

    /**
     * Snapshot gates (reviews F1 + F6): base mismatch and freshness —
     * both fail closed before any write.
     *
     * @throws BaseException
     */
    protected function guardSnapshot(FxRateSnapshot $snapshot, string $baseCode): void
    {
        if ($snapshot->baseCode !== $baseCode) {
            throw new BaseException(self::ERROR_BASE_MISMATCH, 502);
        }

        $publishedAt = Carbon::createFromTimestampUTC($snapshot->timestamp);

        $maxAge = (int) config('core.fx.max_rate_age_hours', 48);

        if ($publishedAt->lt(now()->subHours($maxAge))) {
            throw new BaseException(self::ERROR_STALE, 502);
        }

        $futureTolerance = (int) config('core.fx.future_tolerance_minutes', 10);

        if ($publishedAt->gt(now()->addMinutes($futureTolerance))) {
            throw new BaseException(self::ERROR_FUTURE, 502);
        }
    }

    /**
     * Phase 1 (outside any transaction): format every applicable rate.
     * Missing, exponent-notation, malformed, zero and overflow rates
     * are skipped per currency (review F16) — the document itself was
     * already validated structurally by the provider. Skips are
     * RETURNED so they reach the status record (review F15).
     *
     * @return array{updates: array<string, array{id: int, from: string, to: string}>, skipped: list<string>}
     */
    protected function buildUpdateSet(FxRateSnapshot $snapshot): array
    {
        $candidates = Currency::query()
            ->where(CurrencySchema::IS_ACTIVE, true)
            ->where(CurrencySchema::IS_DEFAULT, false)
            ->get()
            ->keyBy(CurrencySchema::CODE);

        $updateSet = [];
        $skipped = [];

        foreach ($candidates as $code => $currency) {
            $raw = $snapshot->rates[$code] ?? null;

            if ($raw === null || FxRateFormatter::isExponentNotation($raw)) {
                $skipped[] = $code;

                continue;
            }

            $formatted = FxRateFormatter::format($raw);

            if ($formatted === null) {
                $skipped[] = $code;

                continue;
            }

            $updateSet[$code] = [
                'id' => $currency->{CurrencySchema::ID},
                'from' => (string) $currency->{CurrencySchema::EXCHANGE_RATE},
                'to' => $formatted,
            ];
        }

        return ['updates' => $updateSet, 'skipped' => $skipped];
    }

    /**
     * Phase 2 — ONE transaction under the shared currency lock protocol
     * (F18): lock every currency row in ascending id order, revalidate
     * the base and each target against the LOCKED state, update, write
     * success status, commit. Any failure rolls back with zero changes.
     *
     * @param  array<string, array{id: int, from: string, to: string}>  $updateSet
     * @param  list<string>  $phaseSkips  currencies skipped during phase 1
     * @return array<string, mixed> the fresh full status payload
     *
     * @throws BaseException base changed mid-flight or DB failure
     */
    protected function applyRates(FxRateSnapshot $snapshot, string $baseCode, array $updateSet, array $phaseSkips = []): array
    {
        try {
            $applied = DB::transaction(function () use ($baseCode, $updateSet): array {
                // F18 shared lock order — identical to markCurrencyDefault.
                $locked = Currency::query()
                    ->orderBy(CurrencySchema::ID)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(CurrencySchema::ID);

                // The base revalidation happens WHILE holding the locks —
                // a default switch either happened entirely before this
                // transaction (we see it and abort) or waits for us.
                $default = $locked->firstWhere(CurrencySchema::IS_DEFAULT, true);

                if ($default === null || $default->{CurrencySchema::CODE} !== $baseCode) {
                    throw new BaseException(self::ERROR_BASE_MISMATCH, 409);
                }

                $applied = [];

                foreach ($updateSet as $code => $entry) {
                    $currency = $locked->get($entry['id']);

                    // Revalidate against the LOCKED state: the currency may
                    // have been deactivated or promoted between phase 1 and
                    // the lock.
                    if (
                        $currency === null
                        || ! $currency->{CurrencySchema::IS_ACTIVE}
                        || $currency->{CurrencySchema::IS_DEFAULT}
                    ) {
                        continue;
                    }

                    $currency->update([CurrencySchema::EXCHANGE_RATE => $entry['to']]);

                    $applied[] = [
                        'code' => $code,
                        'from' => $entry['from'],
                        'to' => $entry['to'],
                    ];
                }

                return $applied;
            });
        } catch (BaseException $e) {
            throw $e; // base-mismatch abort — recorded by the caller
        } catch (Throwable $e) {
            report($e);

            throw new BaseException(self::ERROR_SYNC_FAILED, 502, previous: $e);
        }

        // Apply-phase skips (a target deactivated/promoted mid-flight)
        // merge with the phase-1 skips for the status record.
        $skipped = array_values(array_unique(array_merge(
            $phaseSkips,
            array_diff(array_keys($updateSet), array_column($applied, 'code')),
        )));

        $status = [
            'last_attempt' => [
                'at' => now()->toIso8601String(),
                'status' => 'success',
                'error_code' => null,
                'provider_timestamp' => $snapshot->timestamp,
                'base_code' => $snapshot->baseCode,
            ],
            'last_success' => [
                'at' => now()->toIso8601String(),
                'provider_timestamp' => $snapshot->timestamp,
                'base_code' => $snapshot->baseCode,
                'updated_count' => count($applied),
                'skipped_count' => count($skipped),
                'updated' => array_slice($applied, 0, self::STATUS_DETAIL_CAP),
                'skipped' => array_slice($skipped, 0, self::STATUS_DETAIL_CAP),
                'truncated' => count($applied) > self::STATUS_DETAIL_CAP
                    || count($skipped) > self::STATUS_DETAIL_CAP,
            ],
        ];

        // Status is diagnostics — a persistence failure here must never
        // fail an already-committed sync.
        try {
            $this->fxSettings->recordSyncStatus($status);
        } catch (Throwable $e) {
            report($e);
        }

        return $status;
    }

    /**
     * Record a failed attempt AFTER everything rolled back, so the
     * diagnostic survives (review F3). Best-effort: diagnostics never
     * mask the original failure.
     *
     * @param  array<string, mixed>|null  $status
     */
    protected function recordFailure(?array $status, string $errorCode): void
    {
        $providerTimestamp = is_array($status['last_success'] ?? null)
            ? ($status['last_success']['provider_timestamp'] ?? null)
            : null;

        try {
            $this->fxSettings->recordSyncStatus([
                'last_attempt' => [
                    'at' => now()->toIso8601String(),
                    'status' => 'failed',
                    'error_code' => $errorCode,
                    'provider_timestamp' => $providerTimestamp,
                    'base_code' => null,
                ],
                'last_success' => $status['last_success'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Only sanitized, stable codes may enter the status record —
     * unknown exception text never does (review F5/F22).
     */
    protected function sanitizeErrorCode(Throwable $e): string
    {
        if ($e instanceof FxProviderException || $e instanceof BaseException) {
            $code = (string) $e->getCode();

            if (preg_match('/^[a-z0-9_.]+$/', $code)) {
                return $code;
            }
        }

        return self::ERROR_SYNC_FAILED;
    }
}
