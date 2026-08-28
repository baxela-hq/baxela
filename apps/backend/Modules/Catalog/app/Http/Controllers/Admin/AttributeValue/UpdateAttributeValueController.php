<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeValue\UpdateAttributeValueAction;
use Modules\Catalog\Http\Requests\Admin\AttributeValue\AttributeValueRequest;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class UpdateAttributeValueController extends Controller
{
    public function __construct(protected UpdateAttributeValueAction $action) {}

    public function __invoke(string $id, string $valueId, AttributeValueRequest $request): AttributeValueResource
    {
        return AttributeValueResource::make($this->action->handle($id, $valueId, $request->validated()));
    }
}
