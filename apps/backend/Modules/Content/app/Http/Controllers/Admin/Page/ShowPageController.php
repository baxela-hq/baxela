<?php

namespace Modules\Content\Http\Controllers\Admin\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Admin\Page\ShowPageAction;
use Modules\Content\Transformers\Admin\Page\PageResource;

class ShowPageController extends Controller
{
    public function __construct(protected ShowPageAction $action) {}

    public function __invoke(string $id, Request $request): PageResource
    {
        return new PageResource($this->action->handle($id));
    }
}
