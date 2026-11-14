<?php

namespace Modules\Core\Actions\Admin\Extension;

use App\Baxela;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Extensions\ComposerPackages;
use Modules\Core\Extensions\ExtensionManager;

/**
 * The marketplace update-check proxy (P5.3): collects the installed
 * extension map, calls the marketplace's public POST /api/packages/updates
 * on the admin API's behalf (the marketplace emits no CORS headers — a
 * browser cannot call it), and answers with an honest availability
 * envelope instead of failing:
 *
 *   ok                        valid marketplace response (fetched or fresh-cached)
 *   marketplace_unreachable   connection failure/timeout, or HTTP 5xx
 *   marketplace_error         any other non-200 (4xx incl. 429, 3xx, 204),
 *                             invalid JSON, or a response without an array `data`
 *   marketplace_unconfigured  empty core.extensions.marketplace_url — feature off
 *
 * `cached` means exactly one thing: the returned data came from cache
 * rather than being fetched during this request. It says nothing about
 * freshness — availability carries that.
 *
 * Stale-while-error: two keys. The fresh key (15 min) serves ordinary
 * cache hits; the stale key (24 h) holds the LAST SUCCESSFUL response
 * and is only ever written after a successful, validated fetch. On
 * failure the stale payload is served read-only with its original
 * generated_at — a failed request can never overwrite the last known
 * good answer, and once the stale TTL lapses the answer is an honest
 * empty map.
 *
 * Known accepted race (documented, deliberate): an update request that
 * starts before a Composer mutation and finishes after its invalidation
 * can repopulate the cache with pre-mutation data. Accepted for an
 * advisory, low-volume badge; a cache-generation token is the fix if
 * this ever needs stronger guarantees.
 *
 * Only HTTP 200 counts as success, and redirects are NOT followed — a
 * 3xx from the marketplace classifies as marketplace_error rather than
 * silently resolving to its target.
 */
class CheckExtensionUpdatesAction
{
    public const string CACHE_KEY = 'core.extension-updates';

    public const string STALE_KEY = 'core.extension-updates.stale';

    public function __construct(
        protected ExtensionManager $manager,
        protected ComposerPackages $packages,
    ) {}

    /**
     * Forget both cache keys. Callers invoke this immediately after
     * every SUCCESSFUL Composer operation that changes the installed
     * package set (baxela:install/remove/update, the install rollback's
     * remove) and after extension toggles — never deferred to command
     * completion, so a later failure cannot leave a stale answer
     * surviving a changed installation.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::STALE_KEY);
    }

    /**
     * @return array{availability: string, cached: bool, generated_at: ?string, data: array<string, mixed>}
     */
    public function handle(): array
    {
        $url = rtrim((string) config('core.extensions.marketplace_url'), '/');

        if ($url === '') {
            return $this->envelope('marketplace_unconfigured', false, null, []);
        }

        $installed = $this->installedMap();

        // Nothing installed → nothing to check; a successful empty
        // answer without an outbound request.
        if ($installed === []) {
            return $this->envelope('ok', false, null, []);
        }

        $fresh = Cache::get(self::CACHE_KEY);

        if (is_array($fresh)) {
            return $this->envelope('ok', true, $fresh['generated_at'] ?? null, $fresh['data'] ?? []);
        }

        return $this->fetch($url, $installed);
    }

    /**
     * @param  array<string, string>  $installed  slug => semver
     * @return array{availability: string, cached: bool, generated_at: ?string, data: array<string, mixed>}
     */
    protected function fetch(string $url, array $installed): array
    {
        try {
            $response = Http::timeout((int) config('core.extensions.http.timeout', 5))
                ->connectTimeout(3)
                ->withOptions(['allow_redirects' => false])
                ->acceptJson()
                ->post($url.'/api/packages/updates', [
                    'core' => Baxela::VERSION,
                    'installed' => $installed,
                ]);
        } catch (ConnectionException $e) {
            return $this->degraded('marketplace_unreachable', $e->getMessage());
        }

        if ($response->status() >= 500) {
            return $this->degraded('marketplace_unreachable', 'HTTP '.$response->status());
        }

        // Only 200 is success — 3xx, 4xx (incl. the P5.5 throttle's 429)
        // and non-200 2xx are a reachable marketplace refusing/answering
        // unexpectedly.
        if ($response->status() !== 200) {
            return $this->degraded('marketplace_error', 'HTTP '.$response->status());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            return $this->degraded('marketplace_error', 'invalid response envelope');
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        // Stale first, then fresh: a concurrent reader between the two
        // writes sees at worst a fresh-miss (extra fetch), never a
        // stale pointer to data newer than fresh.
        Cache::put(self::STALE_KEY, $payload, now()->addHours(24));
        Cache::put(self::CACHE_KEY, $payload, now()->addMinutes(15));

        return $this->envelope('ok', false, $payload['generated_at'], $data);
    }

    /**
     * Serve the last successful response read-only when present;
     * otherwise an honest empty map. Never writes, never rewrites the
     * stale entry's timestamp.
     *
     * @return array{availability: string, cached: bool, generated_at: ?string, data: array<string, mixed>}
     */
    protected function degraded(string $availability, string $reason): array
    {
        Log::debug('Extension update check degraded.', ['availability' => $availability, 'reason' => $reason]);

        $stale = Cache::get(self::STALE_KEY);

        if (is_array($stale)) {
            return $this->envelope($availability, true, $stale['generated_at'] ?? null, $stale['data'] ?? []);
        }

        return $this->envelope($availability, false, null, []);
    }

    /**
     * The installed-extension map the marketplace contract requires:
     * slug => semver. Slugs without a live Composer version (leftover
     * registry rows) are skipped — the contract rejects non-semver
     * values, and there is nothing update-checkable about them anyway.
     *
     * @return array<string, string>
     */
    protected function installedMap(): array
    {
        $slugs = collect(array_keys($this->manager->all()))
            ->merge($this->packages->extensionSlugs())
            ->unique()
            ->sort()
            ->values();

        $installed = [];

        foreach ($slugs as $slug) {
            $version = $this->packages->version($slug);

            if ($version !== null) {
                $installed[$slug] = $version;
            }
        }

        return $installed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{availability: string, cached: bool, generated_at: ?string, data: array<string, mixed>}
     */
    protected function envelope(string $availability, bool $cached, ?string $generatedAt, array $data): array
    {
        return [
            'availability' => $availability,
            'cached' => $cached,
            'generated_at' => $generatedAt,
            'data' => $data,
        ];
    }
}
