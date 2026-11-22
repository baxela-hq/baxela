<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Schemas\Product\ProductStatusEnum;
use Modules\Catalog\Schemas\Search\SearchCoordinationSchema;

/**
 * The per-product index writer (v17 F3 ordering,
 * postgres-migration §3.2.4): lease (product, generation) → re-read the
 * operation under the lease → resolve its immutable target → fresh read →
 * submit to THAT uid only → await terminal under the renewed lease →
 * resolve the operation. Every coordination write is generation-fenced
 * (CAS on the operation's own generation) — a superseded writer's
 * success against G never touches G+1 bookkeeping.
 *
 * Lease semantics (§3.2.4): a claim requires the claimant's FRESH
 * heartbeat; reclaiming another writer's lease requires BOTH lease
 * expiry AND that writer's stale heartbeat — a paused-but-alive worker
 * keeps its lease. Writers re-read the journal inside the lease and
 * write journal-derived state (stamped with the per-product cursor) —
 * never a payload captured before the lease, so a resumed stale writer
 * re-materializes current state (convergence-to-journal, §3.4).
 */
final class ProductIndexWriter
{
    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchGenerationGuard $guard,
        private readonly ProductDocumentBuilder $builder,
        private readonly int $leaseTtlSeconds = 300,
        private readonly int $workerStaleAfterSeconds = 60,
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
        // Peek only to learn the lease's generation scope — the operation
        // itself is re-read under the lease (journal-derived writes).
        $peek = $this->latestUnresolvedOperation($productId);

        if ($peek === null) {
            return true; // Nothing journaled for this product — nothing to do.
        }

        $generation = (int) $peek->{SearchCoordinationSchema::GENERATION};
        $owner = 'writer:'.OwnerIncarnation::instanceToken().':'.bin2hex(random_bytes(4));

        if (! $this->acquireLease($productId, $generation, $owner)) {
            // Another (product, generation)-scoped writer holds it; the
            // journal entry remains unresolved and is retried.
            return false;
        }

        try {
            // Authoritative re-read UNDER the lease (§3.2.4): the peek may
            // be stale — the payload is built from this read, never from
            // pre-lease state.
            $operation = $this->latestUnresolvedOperation($productId);

            if ($operation === null) {
                return true; // Resolved by someone else while we claimed.
            }

            if ($operation->{SearchCoordinationSchema::SUPERSEDED_AT} !== null) {
                return true; // Superseded by a cutover: its target is retired.
            }

            if ((int) $operation->{SearchCoordinationSchema::GENERATION} !== $generation) {
                return true; // The journal moved generations; the new one owns the lease scope.
            }

            $targetUid = (string) $operation->{SearchCoordinationSchema::TARGET_INDEX_UID};

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

            $cursor = app(SearchCursorManager::class)->cursorFor($productId);

            // Journal-derived payload, stamped with the per-product cursor
            // it was built from (the reconciler's drift key).
            $document = [...$this->builder->build($product), 'cursor' => $cursor ?? 0];

            $taskUid = $eligible
                ? $this->client->submitDocuments($targetUid, [$document])
                : $this->client->submitDeletion($targetUid, $productId);

            // Persist the UID (owner-checked CAS — review v20 F2): between
            // submission and persistence the operation is ambiguous and
            // cleanup-blocking; after persistence it is awaitable.
            $this->persistTaskUid($operation->id, $owner, $taskUid);

            // Lease renewal across the awaited task (TTL is 3x the await
            // budget; renewal closes the gap regardless).
            $this->renewLease($productId, $generation, $owner);

            $terminalSuccess = $this->client->awaitTask($taskUid);

            $this->renewLease($productId, $generation, $owner);

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

    /**
     * Claim the (product, generation) lease (§3.2.4): the claimant's
     * heartbeat must be fresh; taking another writer's lease requires
     * BOTH its expiry AND its stale heartbeat (paused-but-alive workers
     * keep their leases).
     */
    private function acquireLease(int $productId, int $generation, string $owner): bool
    {
        SearchWorkerHeartbeat::upsert();

        $now = now();
        $leaseUntil = $now->copy()->addSeconds($this->leaseTtlSeconds);
        $token = OwnerIncarnation::instanceToken();

        // 1) Free/absent or OUR OWN previous lease: take it unconditionally.
        $claimed = DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(fn ($q) => $q
                ->whereNull(SearchCoordinationSchema::OWNER)
                ->orWhere(SearchCoordinationSchema::INSTANCE_TOKEN, $token))
            ->update([
                SearchCoordinationSchema::OWNER => $owner,
                SearchCoordinationSchema::INSTANCE_TOKEN => $token,
                SearchCoordinationSchema::LEASE_UNTIL => $leaseUntil,
                SearchCoordinationSchema::ACQUIRED_AT => $now,
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
                    SearchCoordinationSchema::INSTANCE_TOKEN => $token,
                    SearchCoordinationSchema::LEASE_UNTIL => $leaseUntil,
                    SearchCoordinationSchema::ACQUIRED_AT => $now,
                ]);

                return true;
            } catch (\Throwable) {
                return false; // Lost an insert race — retried by the caller.
            }
        }

        // 2) Another writer's lease: reclaimable only when expired AND its
        // heartbeat is stale — the conditional UPDATE is the race-safe
        // authority (a fresh heartbeat blocks the reclaim even mid-check).
        $expired = $now->copy()->parse((string) $existing->{SearchCoordinationSchema::LEASE_UNTIL})->isPast();

        if (! $expired) {
            return false;
        }

        if (SearchWorkerHeartbeat::isFresh(
            (string) $existing->{SearchCoordinationSchema::INSTANCE_TOKEN},
            $this->workerStaleAfterSeconds,
        )) {
            return false; // Paused-but-alive: keep its lease.
        }

        $reclaimed = DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, (string) $existing->{SearchCoordinationSchema::INSTANCE_TOKEN})
            ->where(SearchCoordinationSchema::LEASE_UNTIL, $existing->{SearchCoordinationSchema::LEASE_UNTIL})
            ->update([
                SearchCoordinationSchema::OWNER => $owner,
                SearchCoordinationSchema::INSTANCE_TOKEN => $token,
                SearchCoordinationSchema::LEASE_UNTIL => $leaseUntil,
                SearchCoordinationSchema::ACQUIRED_AT => $now,
            ]);

        return $reclaimed === 1;
    }

    /** Token-guarded lease renewal while awaiting engine tasks. */
    private function renewLease(int $productId, int $generation, string $owner): void
    {
        DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(SearchCoordinationSchema::OWNER, $owner)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, OwnerIncarnation::instanceToken())
            ->update([SearchCoordinationSchema::LEASE_UNTIL => now()->addSeconds($this->leaseTtlSeconds)]);
    }

    private function releaseLease(int $productId, int $generation, string $owner): void
    {
        DB::table(SearchCoordinationSchema::LEASES_TABLE)
            ->where(SearchCoordinationSchema::PRODUCT_ID, $productId)
            ->where(SearchCoordinationSchema::GENERATION, $generation)
            ->where(SearchCoordinationSchema::OWNER, $owner)
            ->where(SearchCoordinationSchema::INSTANCE_TOKEN, OwnerIncarnation::instanceToken())
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
