<?php

namespace Modules\Catalog\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\Option\ShowOptionAction;
use Modules\Catalog\Transformers\Admin\Option\OptionResource;

class ShowOptionController extends Controller
{
    public function __construct(protected ShowOptionAction $action) {}

    public function __invoke(string $id, Request $request): OptionResource
    {
        return OptionResource::make($this->action->handle($id));
    }
}
