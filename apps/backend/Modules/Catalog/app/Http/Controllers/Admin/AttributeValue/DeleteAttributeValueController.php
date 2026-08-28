<?php

namespace Modules\Catalog\Http\Controllers\Admin\AttributeValue;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\AttributeValue\DeleteAttributeValueAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteAttributeValueController extends Controller
{
    public function __construct(protected DeleteAttributeValueAction $action) {}

    public function __invoke(string $id, string $valueId): Response
    {
        $this->action->handle($valueId);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
