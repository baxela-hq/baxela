<?php

namespace Modules\Catalog\Search;

use GuzzleHttp\Client as GuzzleClient;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\TimeOutException;
use Modules\Core\Support\Money;
use Throwable;

/**
 * Meilisearch adapter. Target-uid resolution happens OUTSIDE this class
 * (the caller passes the generation uid — immutable operation targeting,
 * v17 F3); this class speaks only engine protocol.
 *
 * Failure contract (review F3/v10): expected infrastructure failures
 * (connection, timeout, Meili 5xx) normalize into
 * SearchInfrastructureException so callers degrade to SQL; any other
 * throwable is a programming error and propagates. A 4xx ApiError (e.g.
 * a malformed filter) is a bug, not weather.
 */
final class MeilisearchSearchClient implements SearchClientInterface
{
    private readonly ?Client $client;

    public function __construct(
        string $host,
        string $apiKey,
        private readonly int $requestTimeoutSeconds,
        private readonly int $taskAwaitSeconds,
        private readonly SearchIndexSettings $settings,
    ) {
        if ($host === '') {
            $this->client = null;

            return;
        }

        // One client for search, submission, polling and health — every
        // request bounded by explicit connect/request timeouts at the
        // transport layer (v16 F4); the SDK client gets the PSR-18
        // client injected, retries off.
        $http = new GuzzleClient([
            'connect_timeout' => min(3, $this->requestTimeoutSeconds),
            'timeout' => $this->requestTimeoutSeconds,
            'http_errors' => false,
        ]);

        $this->client = new Client($host, $apiKey !== '' ? $apiKey : null, $http);
    }

    public function configured(): bool
    {
        return $this->client !== null;
    }

    public function search(ProductSearchQuery $query): ProductSearchResult
    {
        $params = [
            'page' => $query->page,
            'hitsPerPage' => $query->perPage,
            // Exact parity: match only the request locale's title field
            // (the SQL whereHas constrains the translation language too).
            'attributesToSearchOn' => [$query->titleAttribute],
            'attributesToRetrieve' => ['product_id'],
        ];

        $filter = $this->filterExpression($query);

        if ($filter !== '') {
            $params['filter'] = $filter;
        }

        if ($query->sort !== null) {
            $params['sort'] = [$query->sort];
        }

        try {
            $result = $this->client()
                ->index($query->indexUid)
                ->search($query->term, $params);
        } catch (Throwable $e) {
            throw $this->normalize($e);
        }

        $orderedIds = array_map(
            static fn ($hit) => (int) $hit['product_id'],
            $result->getHits(),
        );

        return new ProductSearchResult(
            array_values($orderedIds),
            (int) ($result->getTotalHits() ?? 0),
            $result->getProcessingTimeMs(),
        );
    }

    public function submitDocuments(string $indexUid, array $documents): string
    {
        try {
            return (string) $this->client()
                ->index($indexUid)
                ->updateDocuments($documents, SearchIndexSettings::PRIMARY_KEY)['taskUid'];
        } catch (Throwable $e) {
            throw $this->normalize($e);
        }
    }

    public function submitDeletion(string $indexUid, int $productId): string
    {
        try {
            return (string) $this->client()
                ->index($indexUid)
                ->deleteDocument((string) $productId)['taskUid'];
        } catch (Throwable $e) {
            throw $this->normalize($e);
        }
    }

    public function ensureIndex(string $indexUid): void
    {
        try {
            $task = $this->client()->createIndex($indexUid, [
                'primaryKey' => SearchIndexSettings::PRIMARY_KEY,
            ]);
            $this->client()->waitForTask((string) $task['taskUid'], $this->taskAwaitMs());
            $settingsTask = $this->client()->index($indexUid)->updateSettings(SearchIndexSettings::toArray());
            $this->client()->waitForTask((string) $settingsTask['taskUid'], $this->taskAwaitMs());
        } catch (Throwable $e) {
            throw $this->normalize($e);
        }
    }

    public function deleteIndex(string $indexUid): void
    {
        try {
            $task = $this->client()->deleteIndex($indexUid);
            $this->client()->waitForTask((string) $task['taskUid'], $this->taskAwaitMs());
        } catch (Throwable $e) {
            // A missing index IS the desired post-condition.
            if ($e instanceof ApiException && $e->httpStatus === 404) {
                return;
            }

            throw $this->normalize($e);
        }
    }

    public function taskStatus(string $taskUid): ?string
    {
        try {
            return (string) $this->client()->getTask($taskUid)['status'];
        } catch (Throwable $e) {
            // Unknown uid → the task is not (yet) enumerable; engine down →
            // unknown. Both leave the operation unresolved — callers must
            // never treat NULL as terminal.
            $normalized = $this->normalize($e);

            if ($normalized instanceof ApiException && $normalized->httpStatus === 404) {
                return null;
            }

            throw $normalized;
        }
    }

    public function awaitTask(string $taskUid): bool
    {
        $deadline = microtime(true) + $this->taskAwaitSeconds;

        do {
            $status = $this->taskStatus($taskUid);

            if ($status === 'processed') {
                return true;
            }

            if ($status === 'failed' || $status === 'canceled') {
                return false;
            }

            // 'enqueued'/'processing'/null → still pending; sleep-poll.
            usleep(200_000);
        } while (microtime(true) < $deadline);

        throw new SearchInfrastructureException(sprintf(
            'Meili task %s did not reach a terminal state within %d seconds.',
            $taskUid,
            $this->taskAwaitSeconds,
        ));
    }

    /**
     * The approved filter mapping (review v13/v14 F4): cross-variant
     * price envelope, per-option OR groups, ANDed constraints — exact
     * parity with the two independent price whereHas filters.
     */
    private function filterExpression(ProductSearchQuery $query): string
    {
        $conditions = [];

        if ($query->categoryId !== null) {
            $conditions[] = 'category_ids = '.$query->categoryId;
        }

        if ($query->brandId !== null) {
            $conditions[] = 'brand_id = '.$query->brandId;
        }

        foreach ($query->optionGroups ?? [] as $group) {
            $values = implode(', ', array_map(static fn ($id) => (int) $id, $group));
            $conditions[] = '(option_value_ids IN ['.$values.'])';
        }

        // price_min=X in SQL = EXISTS variant ≥ X ⇔ priciest variant ≥ X.
        if ($query->priceMin !== null) {
            $conditions[] = 'price_max >= '.$this->toMinor($query->priceMin);
        }

        // price_max=X in SQL = EXISTS variant ≤ X ⇔ cheapest variant ≤ X.
        if ($query->priceMax !== null) {
            $conditions[] = 'price_min <= '.$this->toMinor($query->priceMax);
        }

        return implode(' AND ', $conditions);
    }

    /** Major-unit decimal string → minor units (storage precision 2). */
    private function toMinor(string $decimal): int
    {
        return Money::fromDecimal($decimal, 2);
    }

    private function client(): Client
    {
        if ($this->client === null) {
            throw new SearchInfrastructureException('Meilisearch host is not configured.');
        }

        return $this->client;
    }

    private function taskAwaitMs(): int
    {
        return $this->taskAwaitSeconds * 1000;
    }

    private function normalize(Throwable $e): Throwable
    {
        if ($e instanceof SearchInfrastructureException) {
            return $e;
        }

        // Expected infrastructure weather: transport failures, client-side
        // timeouts, engine 5xx. Everything else (4xx contract violations,
        // logic errors) propagates untouched.
        if (
            $e instanceof CommunicationException
            || $e instanceof TimeOutException
            || ($e instanceof ApiException && $e->httpStatus >= 500)
        ) {
            return new SearchInfrastructureException($e->getMessage(), $e->getCode(), $e);
        }

        return $e;
    }
}
