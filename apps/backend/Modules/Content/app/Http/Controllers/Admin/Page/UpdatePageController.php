<?php

namespace Modules\Content\Http\Controllers\Admin\Page;

use App\Http\Controllers\Controller;
use Modules\Content\Actions\Admin\Page\UpdatePageAction;
use Modules\Content\Http\Requests\Admin\Page\PageRequest;
use Modules\Content\Transformers\Admin\Page\PageResource;

class UpdatePageController extends Controller
{
    public function __construct(protected UpdatePageAction $action) {}

    public function __invoke(string $id, PageRequest $request): PageResource
    {
        return new PageResource($this->action->handle($id, $request->validated()));
    }
}
