<?php

namespace Modules\Catalog\Search;

/**
 * A search page: the engine's ordered product ids (relevance or explicit
 * sort) and the INDEX's matching count. Under index drift the total is
 * the indexed match count, not the count of currently-eligible SQL
 * products — hydration filters stale hits, so a page may under-fill
 * (never leak). See Modules/Catalog/docs/search-indexing.md.
 */
final class ProductSearchResult
{
    /**
     * @param  list<int>  $orderedIds  product ids in engine order
     */
    public function __construct(
        public readonly array $orderedIds,
        public readonly int $total,
        public readonly int $processingTimeMs,
    ) {}
}
