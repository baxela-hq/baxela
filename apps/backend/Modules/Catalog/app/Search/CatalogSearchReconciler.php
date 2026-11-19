<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * Generation-scoped journal replay (v16 F1/v17 F2): drives every journal
 * entry to the BUILDING generation uid and records per-entry, per-
 * generation application in `catalog_search_replay_applied` — the ONLY
 * completeness record (the scan cursor is a position, never proof).
 *
 * Replay is idempotent fresh-read: markers are inserted after the
 * covering task's terminal success (CAS/ON-DUPLICATE no-op), coalesced
 * entries in the same transaction as the covering success; a crash
 * between success and marker persistence re-retries the entry. Holes
 * (earlier unapplied, later applied) are retried by id — completeness is
 * never inferred from cursor position.
 */
final class CatalogSearchReconciler
{
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly ProductDocumentBuilder $builder,
    ) {}

    /**
     * Replay journal entries into the building generation. Returns the
     * highest journal entry id processed (the caller's next cursor).
     *
     * @param  list<string>  $taskUids  out-param collecting every submitted
     *                                  task uid so the caller can await them
     */
    public function replayInto(int $targetGeneration, string $targetUid, int $afterJournalId, array &$taskUids = []): int
    {
        $lastId = $afterJournalId;

        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', '>', $afterJournalId)
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($targetGeneration, $targetUid, &$lastId, &$taskUids): void {
                // Coalesce to the latest entry per product: the fresh read
                // of the latest entry covers every earlier one (v17 F2).
                // The covered entry IDs ride with the product so the
                // applied markers record them when the covering task
                // succeeds (v19 F2 transactional requirement).
                $groupedByProduct = [];

                foreach ($entries as $entry) {
                    $groupedByProduct[(int) $entry->{SearchCoordinationSchema::PRODUCT_ID}][] = $entry;
                    $lastId = max($lastId, (int) $entry->id);
                }

                foreach ($groupedByProduct as $entriesForProduct) {
                    $this->applyEntry($targetGeneration, $targetUid, $entriesForProduct, $taskUids);
                }
            });

        return $lastId;
    }

    /**
     * @param  list<object>  $entries  every journal entry for ONE product
     *                                 in this chunk (the covering group)
     * @param  list<string>  $taskUids
     */
    private function applyEntry(int $targetGeneration, string $targetUid, array $entries, array &$taskUids): void
    {
        $latest = $entries[count($entries) - 1];
        $productId = (int) $latest->{SearchCoordinationSchema::PRODUCT_ID};

        $product = Product::query()->find($productId);

        $eligible = $product !== null
            && $product->{ProductSchema::STATUS} === ProductStatusEnum::IN_STOCK
            && (bool) $product->{ProductSchema::IS_PUBLISHED};

        $taskUid = $eligible
            ? $this->client->submitDocuments($targetUid, [$this->builder->build($product)])
            : $this->client->submitDeletion($targetUid, $productId);

        $taskUids[] = $taskUid;

        $success = $this->client->awaitTask($taskUid);

        // Mark covered — idempotent insert, AFTER terminal observation. A
        // failed task means the fresh read did NOT reach the index: the
        // entries stay UNMARKED so the next replay retries them (v12 F4 /
        // review v19 F1). All coalesced entries of the product are marked
        // in one transaction with the covering success (v19 F2).
        if ($success) {
            DB::transaction(function () use ($targetGeneration, $entries, $productId, $taskUid): void {
                foreach ($entries as $entry) {
                    $this->markApplied($targetGeneration, (int) $entry->id, $productId, $taskUid);
                }
            });
        }
    }

    /**
     * The flip gate: every journal entry must be applied (or covered) for
     * the building generation before the pointer may move.
     */
    public function fullyApplied(int $targetGeneration, int $throughJournalId): bool
    {
        $total = DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', '<=', $throughJournalId)
            ->count();

        $applied = DB::table(SearchCoordinationSchema::REPLAY_APPLIED_TABLE)
            ->where(SearchCoordinationSchema::GENERATION, $targetGeneration)
            ->where(SearchCoordinationSchema::JOURNAL_ENTRY_ID, '<=', $throughJournalId)
            ->count();

        // Scan-built products have no journal entries; every ENTRY must be
        // marked. Coalescing makes several entries share one applied row
        // only when their products match — so applied ≥ entries is the
        // normal shape (multiple products), and applied ≥ entries_total
        // is the completeness condition.
        return $applied >= $total;
    }

    private function markApplied(int $generation, int $journalEntryId, int $productId, string $taskUid): void
    {
        DB::table(SearchCoordinationSchema::REPLAY_APPLIED_TABLE)->insertOrIgnore([
            SearchCoordinationSchema::GENERATION => $generation,
            SearchCoordinationSchema::JOURNAL_ENTRY_ID => $journalEntryId,
            SearchCoordinationSchema::PRODUCT_ID => $productId,
            SearchCoordinationSchema::COVERED_BY_OPERATION_ID => null,
            SearchCoordinationSchema::APPLIED_AT => now(),
        ]);
    }
}
