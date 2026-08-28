<?php

namespace Modules\Catalog\Http\Controllers\Admin\OptionValue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\OptionValue\ShowOptionValueAction;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueResource;

class ShowOptionValueController extends Controller
{
    public function __construct(protected ShowOptionValueAction $action) {}

    public function __invoke(string $id, string $valueId, Request $request): OptionValueResource
    {
        return OptionValueResource::make($this->action->handle($id, $valueId));
    }
}
