<?php

namespace Modules\Catalog\Http\Controllers\Public\Sitemap;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Public\Sitemap\ListSitemapAction;
use Modules\Catalog\Transformers\Public\Sitemap\ProductSitemapResource;

class ListSitemapController extends Controller
{
    public function __construct(protected ListSitemapAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return ProductSitemapResource::collection($this->action->handle());
    }
}
