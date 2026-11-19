<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * The per-product index writer (v17 F3 ordering): lease (product,
 * generation) → resolve the operation's immutable target → fresh read →
 * submit to THAT uid only → await terminal under the renewed lease →
 * resolve the operation. Every coordination write is generation-fenced
 * (CAS on the operation's own generation) — a superseded writer's
 * success against G never touches G+1 bookkeeping.
 *
 * The operation row (journal) was written transactionally by the
 * mutation; the writer resolves it by product + unresolved. Fresh-read
 * eligibility decides upsert vs deletion: a product that became
 * unpublished / out-of-stock / deleted produces a DELETION task —
 * absence is the mandatory publication constraint.
 */
final class ProductIndexWriter
{
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchGenerationGuard $guard,
        private readonly ProductDocumentBuilder $builder,
        private readonly int $leaseTtlSeconds = 300,
    ) {}

    /** True when the engine is configured — see the listener contract. */
    public function configured(): bool
    {
        return $this->client->configured();
    }

    /**
     * Index (or de-index) one product from its latest committed state.
     * Returns true when the operation reached terminal success.
     */
    public function write(int $productId): bool
    {
        $operation = $this->latestUnresolvedOperation($productId);

        if ($operation === null) {
            return true; // Nothing journaled for this product — nothing to do.
        }

        if ($operation->{SearchCoordinationSchema::SUPERSEDED_AT} !== null) {
            return true; // Superseded by a cutover: its target is retired.
        }

        $generation = (int) $operation->{SearchCoordinationSchema::GENERATION};
        $targetUid = (string) $operation->{SearchCoordinationSchema::TARGET_INDEX_UID};
        $owner = 'writer:'.getmypid().':'.bin2hex(random_bytes(4));

        if (! $this->acquireLease($productId, $generation, $owner)) {
            // Another (product, generation)-scoped writer holds it; the
            // journal entry remains unresolved and is retried.
            return false;
        }

        try {
            if ($this->guard->isRetired($targetUid)) {
                // The target generation was retired mid-flight: submit
                // nothing — the operation is superseded-inert. Its row
                // stays cleanup-blocking only while the remote outcome is
                // unknown; a known-uid task is awaited below instead.
                $this->markSuperseded($operation->id, $this->guard->servingState()['generation']);

                return true;
            }

            DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
                ->where('id', $operation->id)
                ->whereNull(SearchCoordinationSchema::SUPERSEDED_AT)
                ->update([SearchCoordinationSchema::SUBMISSION_STARTED_AT => now()]);

            $product = Product::query()->find($productId);
            $eligible = $product !== null
                && $product->{ProductSchema::STATUS} === ProductStatusEnum::IN_STOCK
                && (bool) $product->{ProductSchema::IS_PUBLISHED};

            $taskUid = $eligible
                ? $this->client->submitDocuments($targetUid, [$this->builder->build($product)])
                : $this->client->submitDeletion($targetUid, $productId);

            // Persist the UID (owner-checked CAS — review v20 F2): between
            // submission and persistence the operation is ambiguous and
            // cleanup-blocking; after persistence it is awaitable.
            $this->persistTaskUid($operation->id, $owner, $taskUid);

            $terminalSuccess = $this->client->awaitTask($taskUid);

            $this->resolve($operation->id, $owner, $taskUid, $terminalSuccess ? 'processed' : 'failed');

            return $terminalSuccess;
        } catch (SearchInfrastructureException $e) {
            // Expected engine weather: the operation stays unresolved and
            // cleanup-blocking (ambiguous or nonterminal task) — the
            // journal retry / nightly reindex covers it. Never ack.
            Log::warning('Search index write deferred', [
                'product_id' => $productId,
                'reason' => $e->getMessage(),
            ]);

            return false;
        } finally {
            $this->releaseLease($productId, $generation, $owner);
        }
    }

    /**
     * @return object|null latest unresolved (unsuperseded, task not yet
     *                     persisted) operation row for the product
     */
    private function latestUnresolvedOperation(int $productId): ?object
    {
        return DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->whereNull(SearchCoordinationSchema::RESOLVED_AT)
            ->whereNull(SearchCoordinationSchema::SUPERSEDED_AT)
            ->orderByDesc('id')
            ->first();
    }

    private function acquireLease(int $productId, int $generation, string $owner): bool
    {
        $now = now();
        $leaseUntil = $now->copy()->addSeconds($this->leaseTtlSeconds);

        $claimed = DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(fn ($q) => $q
                ->whereNull(SearchCoordinationSchema::OWNER)
                ->orWhere(SearchCoordinationSchema::LEASE_UNTIL, '<', $now))
            ->update([
                SearchCoordinationSchema::OWNER => $owner,
                SearchCoordinationSchema::LEASE_UNTIL => $leaseUntil,
            ]);

        if ($claimed > 0) {
            return true;
        }

        $existing = DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->first();

        if ($existing === null) {
            try {
                DB::table(SearchCoordinationSchema::LEASES_TABLE)->insert([
                    SearchCoordinationSchema::PRODUCT_ID => $productId,
                    SearchCoordinationSchema::GENERATION => $generation,
                    SearchCoordinationSchema::OWNER => $owner,
                    SearchCoordinationSchema::LEASE_UNTIL => $leaseUntil,
                    SearchCoordinationSchema::ACQUIRED_AT => $now,
                ]);

                return true;
            } catch (\Throwable) {
                return false; // Lost an insert race — retried by the caller.
            }
        }

        return false;
    }

    private function releaseLease(int $productId, int $generation, string $owner): void
    {
        DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(SearchCoordinationSchema::OWNER, $owner)
            ->delete();
    }

    /**
     * Persist the task UID — authority comes from the LEASE (the caller
     * holds it; review v20 F2): the operation row records who holds it.
     */
    private function persistTaskUid(int $operationId, string $owner, string $taskUid): void
    {
        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', $operationId)
            ->update([
                SearchCoordinationSchema::OWNER => $owner,
                SearchCoordinationSchema::TASK_UID => $taskUid,
                SearchCoordinationSchema::TASK_STATUS => 'enqueued',
            ]);
    }

    /**
     * Terminal resolution: terminal ≠ successful (v12 F4) — a failed task
     * resolves the operation but never acknowledges anything. Authority:
     * the caller held the lease across the awaited task; the recorded
     * owner must match.
     */
    private function resolve(int $operationId, string $owner, string $taskUid, string $status): void
    {
        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', $operationId)
            ->where(SearchCoordinationSchema::OWNER, $owner)
            ->where(SearchCoordinationSchema::TASK_UID, $taskUid)
            ->update([
                SearchCoordinationSchema::TASK_STATUS => $status,
                SearchCoordinationSchema::RESOLVED_AT => now(),
                // Release the uniqueness key (supersession-or-resolution
                // transition): the product may index again immediately.
                SearchCoordinationSchema::DOC_KEY => null,
            ]);
    }

    private function markSuperseded(int $operationId, int $byGeneration): void
    {
        DB::table(SearchCoordinationSchema::OPERATIONS_TABLE)
            ->where('id', $operationId)
            ->whereNull(SearchCoordinationSchema::SUPERSEDED_AT)
            ->update([
                SearchCoordinationSchema::SUPERSEDED_AT => now(),
                SearchCoordinationSchema::SUPERSEDED_BY_GENERATION => $byGeneration,
                SearchCoordinationSchema::DOC_KEY => null,
            ]);
    }
}
