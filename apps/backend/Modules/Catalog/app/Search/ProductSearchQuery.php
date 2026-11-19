<?php

namespace Modules\Catalog\Search;

/**
 * A catalog search expressed the way the storefront's products endpoint
 * receives it — one page of hits over the documented filter contract
 * (PublicProductQuery). Price bounds arrive as DECIMAL STRINGS in major
 * units; the client converts to minor units for the index comparison
 * (documents carry minor-unit price bounds).
 */
final class ProductSearchQuery
{
    /**
     * @param  string  $indexUid  generation-scoped uid resolved under the
     *                            writer/serving protocol — never guessed
     * @param  string  $titleAttribute  the request locale's title field
     *                                  (strict locale parity with SQL)
     * @param  list<list<int>>|null  $optionGroups  per-option OR groups,
     *                                              ANDed across options
     * @param  string|null  $priceMin  major-unit decimal string
     * @param  string|null  $priceMax  major-unit decimal string
     * @param  string|null  $sort  canonical Meili sort expression
     *                             ('price_min:asc', 'rating_avg:desc', …)
     *                             — null = engine relevance ranking
     */
    public function __construct(
        public readonly string $term,
        public readonly string $indexUid,
        public readonly string $titleAttribute,
        public readonly ?int $categoryId = null,
        public readonly ?int $brandId = null,
        public readonly ?array $optionGroups = null,
        public readonly ?string $priceMin = null,
        public readonly ?string $priceMax = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $sort = null,
    ) {}
}
