<?php

namespace Modules\Catalog\Http\Controllers\Admin\Option;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Option\DeleteOptionAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteOptionController extends Controller
{
    public function __construct(protected DeleteOptionAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
