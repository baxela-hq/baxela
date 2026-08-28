<?php

namespace Modules\Catalog\Http\Controllers\Admin\Attribute;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Attribute\CreateAttributeAction;
use Modules\Catalog\Http\Requests\Admin\Attribute\AttributeRequest;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class CreateAttributeController extends Controller
{
    public function __construct(protected CreateAttributeAction $action) {}

    public function __invoke(AttributeRequest $request): AttributeResource
    {
        return AttributeResource::make($this->action->handle($request->validated()));
    }
}
