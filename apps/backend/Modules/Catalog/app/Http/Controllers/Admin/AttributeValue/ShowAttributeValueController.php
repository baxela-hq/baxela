<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeValue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\AttributeValue\ShowAttributeValueAction;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class ShowAttributeValueController extends Controller
{
    public function __construct(protected ShowAttributeValueAction $action) {}

    public function __invoke(string $id, string $valueId, Request $request): AttributeValueResource
    {
        return AttributeValueResource::make($this->action->handle($id, $valueId));
    }
}
