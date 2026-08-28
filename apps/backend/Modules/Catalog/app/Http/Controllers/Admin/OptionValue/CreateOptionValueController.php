<?php

namespace Modules\Catalog\Http\Controllers\Admin\OptionValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\OptionValue\CreateOptionValueAction;
use Modules\Catalog\Http\Requests\Admin\OptionValue\OptionValueRequest;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueResource;

class CreateOptionValueController extends Controller
{
    public function __construct(protected CreateOptionValueAction $action) {}

    public function __invoke(string $id, OptionValueRequest $request): OptionValueResource
    {
        return OptionValueResource::make($this->action->handle($id, $request->validated()));
    }
}
