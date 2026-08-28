<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeGroup;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeGroup\UpdateAttributeGroupAction;
use Modules\Catalog\Http\Requests\Admin\AttributeGroup\AttributeGroupRequest;
use Modules\Catalog\Transformers\Admin\AttributeGroup\AttributeGroupResource;

class UpdateAttributeGroupController extends Controller
{
    public function __construct(protected UpdateAttributeGroupAction $action) {}

    public function __invoke(string $id, AttributeGroupRequest $request): AttributeGroupResource
    {
        return AttributeGroupResource::make($this->action->handle($id, $request->validated()));
    }
}
