<?php

namespace Modules\Catalog\Search;

/**
 * Catalog-level search-engine boundary. Implementations speak to the
 * engine (Meilisearch) or no-op (unconfigured); callers express catalog
 * operations, never infrastructure specifics. Every task-returning
 * method returns the engine task UID — the CALLER owns the intent/task
 * lifecycle bookkeeping on `catalog_search_operations` (durable intent
 * BEFORE submission, UID persisted after acceptance, terminal awaited
 * under the writer lease).
 */
interface SearchClientInterface
{
    /** True when the engine is configured (host non-empty). */
    public function configured(): bool;

    /**
     * Page-window search: returns the page's ordered product ids and the
     * index's matching count (the two are different under drift — see
     * Modules/Catalog/docs/search-indexing.md).
     */
    public function search(ProductSearchQuery $query): ProductSearchResult;

    /**
     * Submit an upsert-or-insert of the given documents to a generation
     * index; returns the task UID immediately (async engine). The index
     * must exist (ensureIndex).
     *
     * @param  list<array<string, mixed>>  $documents
     */
    public function submitDocuments(string $indexUid, array $documents): string;

    /** Submit a single-document deletion; returns the task UID. */
    public function submitDeletion(string $indexUid, int $productId): string;

    /**
     * Create the index (primaryKey product_id) if missing and apply the
     * canonical versioned settings, awaiting both tasks. Idempotent.
     */
    public function ensureIndex(string $indexUid): void;

    /** Delete an index (retirement cleanup); idempotent. */
    public function deleteIndex(string $indexUid): void;

    /**
     * Remote task status: 'processed'|'failed'|'canceled'|'enqueued'|
     * 'processing', or NULL when the task cannot be determined (unknown
     * uid / engine unreachable). Observation never mutates anything.
     */
    public function taskStatus(string $taskUid): ?string;

    /**
     * Await a task until terminal. Returns true for `processed`, false
     * for `failed`/`canceled`; throws SearchInfrastructureException when
     * the status cannot be determined within the configured await budget.
     */
    public function awaitTask(string $taskUid): bool;
}
