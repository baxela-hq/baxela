<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeTemplate;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeTemplate\UpdateAttributeTemplateAction;
use Modules\Catalog\Http\Requests\Admin\AttributeTemplate\AttributeTemplateRequest;
use Modules\Catalog\Transformers\Admin\AttributeTemplate\AttributeTemplateResource;

class UpdateAttributeTemplateController extends Controller
{
    public function __construct(protected UpdateAttributeTemplateAction $action) {}

    public function __invoke(string $id, AttributeTemplateRequest $request): AttributeTemplateResource
    {
        return AttributeTemplateResource::make($this->action->handle($id, $request->validated()));
    }
}
