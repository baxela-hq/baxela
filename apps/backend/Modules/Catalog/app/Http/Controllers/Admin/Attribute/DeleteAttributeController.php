<?php

namespace Modules\Catalog\Http\Controllers\Admin\Attribute;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Attribute\DeleteAttributeAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteAttributeController extends Controller
{
    public function __construct(protected DeleteAttributeAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
