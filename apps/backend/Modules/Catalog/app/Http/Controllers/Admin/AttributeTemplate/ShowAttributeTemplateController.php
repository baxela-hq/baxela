<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeTemplate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\AttributeTemplate\ShowAttributeTemplateAction;
use Modules\Catalog\Transformers\Admin\AttributeTemplate\AttributeTemplateResource;

class ShowAttributeTemplateController extends Controller
{
    public function __construct(protected ShowAttributeTemplateAction $action) {}

    public function __invoke(string $id, Request $request): AttributeTemplateResource
    {
        return AttributeTemplateResource::make($this->action->handle($id));
    }
}
