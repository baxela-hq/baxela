<?php

namespace Modules\Catalog\Http\Controllers\Admin\OptionValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\OptionValue\UpdateOptionValueAction;
use Modules\Catalog\Http\Requests\Admin\OptionValue\OptionValueRequest;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueResource;

class UpdateOptionValueController extends Controller
{
    public function __construct(protected UpdateOptionValueAction $action) {}

    public function __invoke(string $id, string $valueId, OptionValueRequest $request): OptionValueResource
    {
        return OptionValueResource::make($this->action->handle($id, $valueId, $request->validated()));
    }
}
