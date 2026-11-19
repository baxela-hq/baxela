<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Repositories\Queries\PublicProductQuery;
use Modules\Catalog\Search\CatalogSearchEngine;
use Modules\Catalog\Search\SearchInfrastructureException;
use Modules\Catalog\Support\AppliesProductPromotions;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class ListProductAction extends AbstractProductAction
{
    use ResolvesPublicLanguage;

    public function __construct(
        Product $model,
        protected AppliesProductPromotions $appliesProductPromotions,
        private readonly CatalogSearchEngine $searchEngine,
    ) {
        parent::__construct($model);
    }

    public function handle(Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);
        $term = trim((string) $request->input('filter.title', ''));

        // Engine selection (v14 F5): the Meili path engages only for text
        // searches (filter[title] non-empty) when the engine is configured,
        // never for featured browsing. Expected infrastructure failures
        // degrade to the SQL path — search never 500s because an indexer
        // is down; programming errors propagate.
        if ($term !== '' && $this->searchEngine->enabled() && ! $request->boolean('featured')) {
            try {
                $products = $this->searchPath($request, $term, $perPage);
            } catch (SearchInfrastructureException) {
                $this->searchEngine->logFallback('infrastructure failure');
                $this->searchEngine->sqlFallbackLogLine($term);
                $products = $this->sqlPath($request, $perPage);
            }

            // Promoted in memory (one batched resolution) before
            // transforming — the resources then read effective/base prices
            // off the models.
            $this->appliesProductPromotions->apply(collect($products->items()));

            return $products;
        }

        $products = $this->sqlPath($request, $perPage);

        $this->appliesProductPromotions->apply(collect($products->items()));

        return $products;
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    private function searchPath(Request $request, string $term, int $perPage): LengthAwarePaginator
    {
        return $this->searchEngine->search(
            $term,
            $this->resolvePublicLanguageId($request),
            max((int) $request->input('page', 1), 1),
            $perPage,
            [
                'categories.id' => $request->input('filter.categories.id'),
                'brand.id' => $request->input('filter.brand.id'),
                'option_value_id' => (array) $request->input('filter.option_value_id', []),
                'price_min' => $request->input('filter.price_min'),
                'price_max' => $request->input('filter.price_max'),
            ],
            $request->input('sort'),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    private function sqlPath(Request $request, int $perPage): LengthAwarePaginator
    {
        return app(PublicProductQuery::class, [
            'languageId' => $this->resolvePublicLanguageId($request),
            'featured' => $request->boolean('featured'),
        ])->paginate($perPage);
    }
}
