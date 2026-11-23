<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;
use Modules\Catalog\Search\OwnerIncarnation;
use Modules\Catalog\Search\SearchClientInterface;
use Modules\Catalog\Search\SearchCursorManager;
use Modules\Catalog\Search\SearchGenerationGuard;
use Modules\Catalog\Search\SearchWorkerHeartbeat;
use Throwable;

/**
 * The durable search reconciler (postgres-migration §3.4): an independent,
 * scheduler-registered drift-repair pass. It reads the AUTHORITATIVE
 * journal state and repairs documents whose indexed cursor is older than
 * the per-product journal cursor — including the stale-writer race a
 * lease-holder cannot fix post-hoc (a resumed writer that already lost
 * its lease writes old content; only this command repairs it). It does
 * not depend on any stale worker resuming.
 *
 * Repair mechanism: re-driving the writer for a drifted product (the
 * writer itself claims a fresh lease, re-reads the journal, and submits
 * journal-derived state). Convergence is an SLO, not a guarantee: target
 * ≈ 2 × reconcile interval under the documented assumptions (scheduler
 * granularity, batch scope, engine availability), with the nightly full
 * reindex as the absolute backstop. Meilisearch unavailability pauses
 * convergence (backoff; the DB journal is the untouched source of truth).
 *
 * Fairness (review F2): oldest-lag-first ordering, at least min(all, K)
 * products per pass — no product starves; retries do not jump the queue.
 */
final class ReconcileCatalogSearchCommand extends Command
{
    protected $signature = 'catalog:search:reconcile
        {--force : Run even when the engine is not configured (diagnostics only)}';

    protected $description = 'Repair search-index drift against the journal cursors (independent reconciler)';

    public function handle(
        SearchClientInterface $client,
        SearchGenerationGuard $guard,
        SearchCursorManager $cursors,
    ): int {
        SearchWorkerHeartbeat::upsert();
        // Maintenance: heartbeat rows of long-gone workers holding
        // nothing open (a new row lands on every worker process start).
        SearchWorkerHeartbeat::prune();

        if (! $client->configured()) {
            if (! $this->option('force')) {
                $this->line('Search engine not configured — nothing to reconcile.');

                return self::SUCCESS;
            }
        }

        $serving = $guard->servingState();
        $batch = max(1, (int) config('catalog.search.reconcile_batch', 500));

        // Drift scope, oldest-lag-first: (1) products with unresolved
        // operations (never indexed / ambiguous), then (2) products whose
        // indexed cursor lags the journal cursor (stale-write damage).
        $candidateIds = DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->whereNull(SearchCoordinationSchema::RESOLVED_AT)
            ->whereNull(SearchCoordinationSchema::SUPERSEDED_AT)
            ->orderBy('id')
            ->limit($batch)
            ->pluck(SearchCoordinationSchema::PRODUCT_ID)
            ->unique()
            ->values()
            ->all();

        $laggingIds = $this->laggingProducts($client, $cursors, $serving['uid'], $batch);

        $repairIds = array_values(array_unique([...$candidateIds, ...$laggingIds]));

        if ($repairIds === []) {
            $this->resetFailureMarker();

            return self::SUCCESS;
        }

        $this->info(sprintf('Reconciling %d drifted product(s).', count($repairIds)));

        $repaired = 0;
        $failed = 0;

        foreach ($repairIds as $productId) {
            SearchWorkerHeartbeat::touch();

            try {
                // The writer is the sanctioned repair path: fresh lease,
                // journal re-read under the lease, journal-derived submit.
                $ok = app(\Modules\Catalog\Search\ProductIndexWriter::class)->write((int) $productId);
                $ok ? $repaired++ : $failed++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('search reconcile item failed', [
                    'product_id' => $productId,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Reconciled: %d repaired, %d deferred.', $repaired, $failed));
        $this->recordFailureMarker($failed);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Products whose indexed document (fetched from the engine) carries a
     * cursor older than the journal cursor — the stale-write detector.
     * Engine unavailability yields an empty set (fail closed: convergence
     * pauses, the SLO clock is suspended and surfaced by the metric, the
     * journal stays authoritative).
     *
     * @return list<int>
     */
    private function laggingProducts(
        SearchClientInterface $client,
        SearchCursorManager $cursors,
        string $servingUid,
        int $batch,
    ): array {
        if (! $client->configured()) {
            return [];
        }

        $lagging = [];

        // Recently-touched cursor rows first (oldest operation join), so
        // the drift scan stays bounded by the journal, not the catalog.
        $rows = DB::table(SearchCoordinationSchema::CURSORS_TABLE.' AS c')
            ->join(
                SearchCoordinationSchema::OPERATIONS_TABLE.' AS o',
                'o.id',
                '=',
                'c.'.SearchCoordinationSchema::LAST_OPERATION_ID,
            )
            ->orderBy('o.id')
            ->limit($batch)
            ->get([
                'c.'.SearchCoordinationSchema::PRODUCT_ID.' AS product_id',
                'c.'.SearchCoordinationSchema::CURSOR.' AS cursor',
            ]);

        foreach ($rows as $row) {
            try {
                $document = $client->getDocument($servingUid, (int) $row->product_id);
            } catch (Throwable) {
                return []; // Engine weather: fail closed for this pass.
            }

            $indexedCursor = is_array($document) ? (int) ($document['cursor'] ?? -1) : -1;

            if ($document === null) {
                // Absent document with a live journal cursor: drifted (the
                // product should be indexed) — unless it is intentionally
                // absent (ineligible). The writer resolves eligibility.
                if ((int) $row->cursor > 0) {
                    $lagging[] = (int) $row->product_id;
                }

                continue;
            }

            if ($indexedCursor < (int) $row->cursor) {
                $lagging[] = (int) $row->product_id;
            }
        }

        return $lagging;
    }

    /**
     * Persistent failure counter for the alert threshold (§3.4): N
     * consecutive failing intervals raises the alert signal.
     */
    private function recordFailureMarker(int $failed): void
    {
        if ($failed === 0) {
            $this->resetFailureMarker();

            return;
        }

        cache()->increment('catalog.search.reconcile.failures');
        $streak = (int) cache()->get('catalog.search.reconcile.failures', 1);
        $alertAfter = max(1, (int) config('catalog.search.reconcile_alert_after', 3));

        if ($streak >= $alertAfter) {
            Log::alert('search reconcile convergence breached', [
                'consecutive_failures' => $streak,
                'slo' => sprintf('~%d minutes', 2 * (int) config('catalog.search.reconcile_interval', 300) / 60),
            ]);
        }
    }

    private function resetFailureMarker(): void
    {
        cache()->forget('catalog.search.reconcile.failures');
    }
}
