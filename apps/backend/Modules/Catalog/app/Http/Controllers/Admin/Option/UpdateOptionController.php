<?php

namespace Modules\Catalog\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Option\UpdateOptionAction;
use Modules\Catalog\Http\Requests\Admin\Option\OptionRequest;
use Modules\Catalog\Transformers\Admin\Option\OptionResource;

class UpdateOptionController extends Controller
{
    public function __construct(protected UpdateOptionAction $action) {}

    public function __invoke(string $id, OptionRequest $request): OptionResource
    {
        return OptionResource::make($this->action->handle($id, $request->validated()));
    }
}
