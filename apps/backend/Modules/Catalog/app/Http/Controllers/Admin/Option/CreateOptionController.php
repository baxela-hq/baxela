<?php

namespace Modules\Catalog\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Option\CreateOptionAction;
use Modules\Catalog\Http\Requests\Admin\Option\OptionRequest;
use Modules\Catalog\Transformers\Admin\Option\OptionResource;

class CreateOptionController extends Controller
{
    public function __construct(protected CreateOptionAction $action) {}

    public function __invoke(OptionRequest $request): OptionResource
    {
        return OptionResource::make($this->action->handle($request->validated()));
    }
}
