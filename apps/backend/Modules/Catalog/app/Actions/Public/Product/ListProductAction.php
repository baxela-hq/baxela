<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Repositories\Queries\PublicProductQuery;
use Modules\Catalog\Support\AppliesProductPromotions;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class ListProductAction extends AbstractProductAction
{
    use ResolvesPublicLanguage;

    public function __construct(Product $model, protected AppliesProductPromotions $appliesProductPromotions)
    {
        parent::__construct($model);
    }

    public function handle(Request $request): LengthAwarePaginator
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        $products = app(PublicProductQuery::class, [
            'languageId' => $this->resolvePublicLanguageId($request),
            'featured' => $request->boolean('featured'),
        ])->paginate($perPage);

        // Promoted in memory (one batched resolution) before transforming —
        // the resources then read effective/base prices off the models
        $this->appliesProductPromotions->apply(collect($products->items()));

        return $products;
    }
}
