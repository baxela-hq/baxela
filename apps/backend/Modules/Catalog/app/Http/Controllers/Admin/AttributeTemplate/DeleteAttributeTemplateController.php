<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeTemplate;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeTemplate\DeleteAttributeTemplateAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteAttributeTemplateController extends Controller
{
    public function __construct(protected DeleteAttributeTemplateAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
