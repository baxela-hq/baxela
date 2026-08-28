<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeTemplate;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeTemplate\CreateAttributeTemplateAction;
use Modules\Catalog\Http\Requests\Admin\AttributeTemplate\AttributeTemplateRequest;
use Modules\Catalog\Transformers\Admin\AttributeTemplate\AttributeTemplateResource;

class CreateAttributeTemplateController extends Controller
{
    public function __construct(protected CreateAttributeTemplateAction $action) {}

    public function __invoke(AttributeTemplateRequest $request): AttributeTemplateResource
    {
        return AttributeTemplateResource::make($this->action->handle($request->validated()));
    }
}
