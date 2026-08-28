<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeGroup;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeGroup\CreateAttributeGroupAction;
use Modules\Catalog\Http\Requests\Admin\AttributeGroup\AttributeGroupRequest;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;

class CreateAttributeGroupController extends Controller
{
    public function __construct(protected CreateAttributeGroupAction $action) {}

    public function __invoke(AttributeGroupRequest $request): AttributeGroupResource
    {
        return AttributeGroupResource::make($this->action->handle($request->validated()));
    }
}
