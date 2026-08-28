<?php

namespace Modules\Content\Http\Controllers\Public\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Content\Actions\Public\Page\ListPageAction;
use Modules\Content\Transformers\Public\Page\PageResource;

class ListPageController extends Controller
{
    public function __construct(protected ListPageAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PageResource::collection($this->action->handle($request));
    }
}
