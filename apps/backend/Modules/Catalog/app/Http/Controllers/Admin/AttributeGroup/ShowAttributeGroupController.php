<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeGroup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\AttributeGroup\ShowAttributeGroupAction;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;

class ShowAttributeGroupController extends Controller
{
    public function __construct(protected ShowAttributeGroupAction $action) {}

    public function __invoke(string $id, Request $request): AttributeGroupResource
    {
        return AttributeGroupResource::make($this->action->handle($id));
    }
}
