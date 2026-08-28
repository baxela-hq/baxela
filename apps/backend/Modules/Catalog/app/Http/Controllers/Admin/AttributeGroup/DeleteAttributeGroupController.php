<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeGroup;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeGroup\DeleteAttributeGroupAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteAttributeGroupController extends Controller
{
    public function __construct(protected DeleteAttributeGroupAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
