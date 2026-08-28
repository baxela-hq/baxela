<?php

namespace Modules\Catalog\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Option\ListOptionAction;
use Modules\Catalog\Transformers\Admin\Option\OptionResource;

class ListOptionController extends Controller
{
    public function __construct(protected ListOptionAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return OptionResource::collection($this->action->handle());
    }
}
