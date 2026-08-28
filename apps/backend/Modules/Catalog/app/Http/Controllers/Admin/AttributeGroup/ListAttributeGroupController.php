<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeGroup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\AttributeGroup\ListAttributeGroupAction;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;

class ListAttributeGroupController extends Controller
{
    public function __construct(protected ListAttributeGroupAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return AttributeGroupResource::collection($this->action->handle());
    }
}
