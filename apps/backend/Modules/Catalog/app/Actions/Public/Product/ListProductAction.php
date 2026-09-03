<?php

namespace Modules\Catalog\Actions\Public\Product;

use Illuminate\Http\Request;
use Modules\Catalog\Repositories\Queries\PublicProductQuery;
use Modules\Catalog\Support\ResolvesPublicLanguage;

class ListProductAction extends AbstractProductAction
{
    use ResolvesPublicLanguage;

    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 50);

        return app(PublicProductQuery::class, [
            'languageId' => $this->resolvePublicLanguageId($request),
        ])->paginate($perPage);
    }
}
