<?php

namespace Modules\Content\Http\Controllers\Public\Sitemap;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Public\Sitemap\ListSitemapAction;
use Modules\Content\Transformers\Public\Sitemap\SitemapResource;

class ListSitemapController extends Controller
{
    public function __construct(protected ListSitemapAction $action) {}

    public function __invoke(Request $request): SitemapResource
    {
        return new SitemapResource($this->action->handle());
    }
}
