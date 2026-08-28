<?php

namespace Modules\Content\Http\Controllers\Admin\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Content\Actions\Admin\Page\CreatePageAction;
use Modules\Content\Http\Requests\Admin\Page\PageRequest;
use Modules\Content\Transformers\Admin\Page\PageResource;

class CreatePageController extends Controller
{
    public function __construct(protected CreatePageAction $action) {}

    public function __invoke(PageRequest $request): JsonResponse
    {
        return PageResource::make($this->action->handle($request->validated()))
            ->response()
            ->setStatusCode(201);
    }
}
