<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeValue\CreateAttributeValueAction;
use Modules\Catalog\Http\Requests\Admin\AttributeValue\AttributeValueRequest;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class CreateAttributeValueController extends Controller
{
    public function __construct(protected CreateAttributeValueAction $action) {}

    public function __invoke(string $id, AttributeValueRequest $request): AttributeValueResource
    {
        return AttributeValueResource::make($this->action->handle($id, $request->validated()));
    }
}
