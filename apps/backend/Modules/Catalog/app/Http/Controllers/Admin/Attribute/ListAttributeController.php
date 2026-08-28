<?php

namespace Modules\Catalog\Http\Controllers\Admin\Attribute;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\Attribute\ListAttributeAction;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class ListAttributeController extends Controller
{
    public function __construct(protected ListAttributeAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return AttributeResource::collection($this->action->handle());
    }
}
