<?php

namespace Modules\Catalog\Search;

/**
 * Canonical, versioned index settings — the single source of truth for
 * how a generation index is configured (review F6/v10). Applied
 * deterministically by `catalog:search:install` and by every rebuild
 * generation; NEVER applied per-request or per-boot. A settings change
 * bumps SETTINGS_VERSION and re-runs install/reindex (documented
 * operational step).
 *
 * Filter parity with PublicProductQuery is the contract; see the
 * mapping table in Modules/Catalog/docs/search-indexing.md.
 */
final class SearchIndexSettings
{
    public const int VERSION = 1;

    public const string PRIMARY_KEY = 'product_id';

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'searchableAttributes' => ['title_*'],
            'filterableAttributes' => [
                'category_ids', 'brand_id', 'option_value_ids',
                'price_min', 'price_max', 'featured_position',
            ],
            'sortableAttributes' => [
                'price_min', 'price_max', 'rating_avg', 'created_at',
                'product_id', 'featured_position',
            ],
            // Meili's default rules, then the curated-featured tiebreak:
            // relevance first, featured position as the final nudge.
            'rankingRules' => [
                'words', 'typo', 'proximity', 'attribute', 'sort',
                'exactness', 'featured_position:asc',
            ],
            'pagination' => [
                // The explicit, observable result ceiling (documented
                // limitation): totals report this once matching exceeds it.
                'maxTotalHits' => 50_000,
            ],
        ];
    }
}
