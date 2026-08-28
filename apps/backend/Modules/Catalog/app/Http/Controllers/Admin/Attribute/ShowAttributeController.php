<?php

namespace Modules\Catalog\Http\Controllers\Admin\Attribute;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\Attribute\ShowAttributeAction;
use Modules\Catalog\Transformers\Admin\Attribute\AttributeResource;

class ShowAttributeController extends Controller
{
    public function __construct(protected ShowAttributeAction $action) {}

    public function __invoke(string $id, Request $request): AttributeResource
    {
        return AttributeResource::make($this->action->handle($id));
    }
}
