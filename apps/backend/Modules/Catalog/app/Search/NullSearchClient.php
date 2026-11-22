<?php

namespace Modules\Catalog\Search;

use Illuminate\Support\Facades\Log;

/**
 * The unconfigured engine: host empty. Every operation no-ops; the
 * house-style warning is logged ONCE per process (not per request) —
 * the search endpoint falls back to SQL and the log line is how an
 * operator notices the engine was expected but is not configured.
 */
final class NullSearchClient implements SearchClientInterface
{
    private bool $warned = false;

    public function configured(): bool
    {
        return false;
    }

    public function search(ProductSearchQuery $query): ProductSearchResult
    {
        $this->warnOnce();

        // No engine → no hits; callers must not call search() when
        // unconfigured (engine selection checks configured() first), so
        // reaching here is a caller bug worth surfacing loudly.
        throw new SearchInfrastructureException('Search engine is not configured.');
    }

    public function submitDocuments(string $indexUid, array $documents): string
    {
        $this->warnOnce();

        return '';
    }

    public function submitDeletion(string $indexUid, int $productId): string
    {
        $this->warnOnce();

        return '';
    }

    public function ensureIndex(string $indexUid): void
    {
        $this->warnOnce();
    }

    public function deleteIndex(string $indexUid): void
    {
        $this->warnOnce();
    }

    public function taskStatus(string $taskUid): ?string
    {
        return null;
    }

    public function awaitTask(string $taskUid): bool
    {
        return false;
    }

    private function warnOnce(): void
    {
        if ($this->warned) {
            return;
        }

        $this->warned = true;

        Log::warning('Search engine skipped: MEILISEARCH_HOST is not configured (set it and run catalog:search:install + catalog:reindex).');
    }

    public function getDocument(string $indexUid, int $productId): ?array
    {
        return null;
    }
}
