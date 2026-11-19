<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Search\CatalogSearchReconciler;
use Modules\Catalog\Search\ProductDocumentBuilder;
use Modules\Catalog\Search\SearchClientInterface;
use Modules\Catalog\Search\SearchGenerationGuard;
use Modules\Catalog\Search\SearchMutationGate;
use Throwable;

/**
 * Builds a fresh generation index from the database and publishes it via
 * the pointer flip — the full approved cutover protocol (v14 F1):
 *
 *   build G+1 (scan + journal replay, awaited)          [G serves]
 *   close admission gate → drain admitted transactions  [barrier]
 *   final journal replay into G+1 (stable closed set)   [barrier]
 *   await every final G+1 task terminal-success         [barrier]
 *   completeness assert via catalog_search_replay_applied
 *   pointer flip (atomic, gate-row lock, fencing)       [linearization]
 *   reopen admissions
 *
 * Any failure before the flip aborts safely: G keeps serving, the journal
 * stays retryable, admissions reopen under the gate-row lock, the staging
 * uid goes cleanup-pending, and the consumed generation number is never
 * reused. This command is also the nightly drift-catch-up safety net.
 */
class ReindexCatalogSearchCommand extends Command
{
    protected $signature = 'catalog:reindex';

    protected $description = 'Build a fresh search generation from the database and atomically flip the serving pointer';

    public function __construct(
        private readonly SearchGenerationGuard $guard,
        private readonly SearchMutationGate $gate,
        private readonly SearchClientInterface $client,
        private readonly ProductDocumentBuilder $documentBuilder,
        private readonly CatalogSearchReconciler $reconciler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $owner = 'reindex:'.getmypid().':'.bin2hex(random_bytes(4));

        $build = $this->guard->allocateBuildGeneration($owner);
        $uid = $build['uid'];
        $generation = $build['generation'];

        $this->info("Building generation {$generation} ({$uid})…");

        try {
            $this->client->ensureIndex($uid);
        } catch (Throwable $e) {
            $this->error("ensureIndex failed: {$e->getMessage()} — serving generation untouched.");

            return self::FAILURE;
        }

        try {
            // --- 1. Full eligible scan (fresh reads) into the new uid ---
            $taskUids = [];
            $scanned = 0;

            Product::query()
                ->where(ProductSchema::STATUS, ProductStatusEnum::IN_STOCK)
                ->where(ProductSchema::IS_PUBLISHED, true)
                ->with(['translations', 'categories', 'variants.optionValues', 'featuredItem'])
                ->chunkById(500, function ($products) use ($uid, &$taskUids, &$scanned): void {
                    $documents = $products
                        ->map(fn ($product) => $this->documentBuilder->build($product))
                        ->all();

                    if ($documents !== []) {
                        $taskUids[] = $this->client->submitDocuments($uid, $documents);
                        $scanned += count($documents);
                    }
                });

            $this->info("Scanned {$scanned} eligible products.");

            // --- 2. Journal replay (idempotent fresh reads; the applied
            //        markers are the completeness record) ---
            $replayCursor = $this->reconciler->replayInto($generation, $uid, 0, $taskUids);
            $this->info("Journal replayed through entry {$replayCursor}.");

            // --- 3. Final barrier: close → drain → final replay → await
            //        → completeness assert → flip → reopen ---
            $this->info('Closing the admission gate (final barrier)…');
            $this->guard->closeAdmissions($owner);
            $this->gate->drain();
            $this->info('Admissions drained.');

            $finalCursor = $this->reconciler->replayInto($generation, $uid, $replayCursor, $taskUids);

            foreach ($taskUids as $taskUid) {
                if (! $this->client->awaitTask($taskUid)) {
                    throw new \RuntimeException("Generation task {$taskUid} failed — refusing to flip.");
                }
            }

            if (! $this->reconciler->fullyApplied($generation, $finalCursor)) {
                throw new \RuntimeException('Replay completeness assert failed — refusing to flip.');
            }

            $this->guard->publish($generation, $uid, $owner);
            $this->guard->openAdmissions();

            $this->info("Generation {$generation} is now serving ({$uid}); admissions reopened.");

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Abort path (v14 F5): G keeps serving, journal stays retryable,
            // admissions reopen atomically, the staging uid goes
            // cleanup-pending and is never reused.
            $this->error("Reindex aborted before the flip: {$e->getMessage()} — serving generation untouched.");

            $this->guard->openAdmissions();

            $this->warn("Staging uid {$uid} (generation {$generation}) abandoned — cleanup-pending, number consumed permanently.");

            return self::FAILURE;
        }
    }
}
