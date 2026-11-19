<?php

namespace Modules\Catalog\Search;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Catalog\Models\OptionValue;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Repositories\Queries\PublicProductQuery;
use Modules\Catalog\Schemas\OptionValue\OptionValueSchema;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Core\Schemas\Language\LanguageSchema;

/**
 * The engine boundary behind the products endpoint (v14 F5: NO new
 * endpoint — the engine is selected inside the action). Meili path:
 * page-window search → ordered id hydration through the forced public
 * scopes → relevance-order restoration → PHP paginator (exact totals
 * against the page contract; under-fill accepted under drift, never a
 * leak). SQL fallback on expected infrastructure failures only.
 */
final class CatalogSearchEngine
{
    /** Storefront sort map → canonical Meili sort expressions (v13 table). */
    private const array SORTS = [
        'price' => 'price_min:asc',
        '-price' => 'price_max:desc',
        'created_at' => 'created_at:asc',
        '-created_at' => 'created_at:desc',
        'rating' => 'rating_avg:desc',
        '-rating' => 'rating_avg:asc',
        'id' => 'product_id:asc',
        '-id' => 'product_id:desc',
    ];

    public function __construct(
        private readonly SearchClientInterface $client,
        private readonly SearchGenerationGuard $guard,
    ) {}

    /** True when the Meili path may serve a `q` search. */
    public function enabled(): bool
    {
        return $this->client->configured();
    }

    /**
     * @param  array<string, mixed>  $filters  the PublicProductQuery filter
     *                                         contract: title handled by the
     *                                         term; categories.id, brand.id,
     *                                         option_value_id (list), price_min,
     *                                         price_max
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(
        string $term,
        ?int $languageId,
        int $page,
        int $perPage,
        array $filters = [],
        ?string $sort = null,
    ): LengthAwarePaginator {
        $startTime = microtime(true);

        $result = $this->client->search(
            $this->buildQuery($term, $languageId, $page, $perPage, $filters, $sort),
        );

        // Hydrate the page's ids (stale/ineligible ids drop here — the
        // second safety boundary; nothing ineligible can leak).
        $products = PublicProductQuery::hydrationQuery($result->orderedIds)
            ->get()
            ->keyBy(ProductSchema::ID);

        $ordered = [];

        foreach ($result->orderedIds as $id) {
            if (isset($products[$id])) {
                $ordered[] = $products[$id];
            }
        }

        Log::info('catalog search served', [
            'engine' => 'meili',
            'latency_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'hits' => count($ordered),
            'total' => $result->total,
        ]);

        return new LengthAwarePaginator(
            $ordered,
            $result->total,
            $perPage,
            $page,
        );
    }

    /** Diagnostic/fallback logging helper for the SQL path. */
    public function logFallback(string $reason): void
    {
        Log::warning('catalog search fell back to sql', ['reason' => $reason]);
    }

    public function sqlFallbackLogLine(string $term): void
    {
        Log::info('catalog search served', [
            'engine' => 'sql',
            'term' => $term,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildQuery(
        string $term,
        ?int $languageId,
        int $page,
        int $perPage,
        array $filters,
        ?string $sort,
    ): ProductSearchQuery {
        // Strict locale parity: match only the request locale's title
        // field, exactly like the SQL whereHas language constraint.
        $titleAttribute = 'title_en';

        if ($languageId !== null) {
            $code = DB::table(LanguageSchema::TABLE)
                ->where('id', $languageId)
                ->value('code');

            $titleAttribute = 'title_'.$code;
        }

        $optionGroups = null;

        if (! empty($filters['option_value_id'])) {
            $ids = collect((array) $filters['option_value_id'])->map(fn ($id) => (int) $id);

            // Group the values per option — same-option OR, cross-option AND
            // (the one-whereHas-per-option semantics, reviewed in v13).
            $groups = OptionValue::query()
                ->whereIn(OptionValueSchema::ID, $ids->all())
                ->get([OptionValueSchema::ID, OptionValueSchema::OPTION_ID])
                ->groupBy(OptionValueSchema::OPTION_ID)
                ->map(fn ($rows) => $rows->pluck(OptionValueSchema::ID)->all())
                ->values()
                ->all();

            $optionGroups = $groups === [] ? null : $groups;
        }

        return new ProductSearchQuery(
            term: $term,
            indexUid: $this->guard->servingIndexUid(),
            titleAttribute: $titleAttribute,
            categoryId: isset($filters['categories.id']) ? (int) $filters['categories.id'] : null,
            brandId: isset($filters['brand.id']) ? (int) $filters['brand.id'] : null,
            optionGroups: $optionGroups,
            priceMin: isset($filters['price_min']) ? (string) $filters['price_min'] : null,
            priceMax: isset($filters['price_max']) ? (string) $filters['price_max'] : null,
            page: $page,
            perPage: $perPage,
            sort: self::SORTS[$sort] ?? null,
        );
    }
}
