<?php

namespace Modules\Catalog\Http\Controllers\Public\Option;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Public\Option\ListOptionAction;
use Modules\Catalog\Transformers\Public\Option\OptionResource;

class ListOptionController extends Controller
{
    public function __construct(protected ListOptionAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return OptionResource::collection($this->action->handle($request));
    }
}
