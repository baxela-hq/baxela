<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeTemplate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\Admin\AttributeTemplate\ListAttributeTemplateAction;
use Modules\Catalog\Transformers\Admin\AttributeTemplate\AttributeTemplateResource;

class ListAttributeTemplateController extends Controller
{
    public function __construct(protected ListAttributeTemplateAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return AttributeTemplateResource::collection($this->action->handle());
    }
}
