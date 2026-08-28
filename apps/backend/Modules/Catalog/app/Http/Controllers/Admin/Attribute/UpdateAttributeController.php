<?php

namespace Modules\Catalog\Http\Controllers\Admin\Attribute;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Attribute\UpdateAttributeAction;
use Modules\Catalog\Http\Requests\Admin\Attribute\AttributeRequest;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class UpdateAttributeController extends Controller
{
    public function __construct(protected UpdateAttributeAction $action) {}

    public function __invoke(string $id, AttributeRequest $request): AttributeResource
    {
        return AttributeResource::make($this->action->handle($id, $request->validated()));
    }
}
