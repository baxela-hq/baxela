<?php

namespace Modules\Content\Http\Controllers\Public\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Public\Page\ShowPageAction;
use Modules\Content\Transformers\Public\Page\PageResource;

class ShowPageController extends Controller
{
    public function __construct(protected ShowPageAction $action) {}

    public function __invoke(string $slug, Request $request): PageResource
    {
        return PageResource::make($this->action->handle($slug));
    }
}
