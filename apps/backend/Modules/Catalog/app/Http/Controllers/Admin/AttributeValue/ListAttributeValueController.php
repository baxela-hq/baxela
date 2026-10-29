<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeValue;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\AttributeValue\ListAttributeValueAction;
use Modules\Catalog\Transformers\Admin\AttributeValue\AttributeValueResource;

class ListAttributeValueController extends Controller
{
    public function __construct(protected ListAttributeValueAction $action) {}

    public function __invoke(string $id, Request $request): AnonymousResourceCollection
    {
        return AttributeValueResource::collection($this->action->handle($id));
    }
}
