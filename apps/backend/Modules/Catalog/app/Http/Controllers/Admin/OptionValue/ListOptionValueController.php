<?php

namespace Modules\Catalog\Http\Controllers\Admin\OptionValue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\OptionValue\ListOptionValueAction;
use Modules\Catalog\Transformers\Admin\OptionValue\OptionValueResource;

class ListOptionValueController extends Controller
{
    public function __construct(protected ListOptionValueAction $action) {}

    public function __invoke(string $id, Request $request): AnonymousResourceCollection
    {
        return OptionValueResource::collection($this->action->handle($id));
    }
}
