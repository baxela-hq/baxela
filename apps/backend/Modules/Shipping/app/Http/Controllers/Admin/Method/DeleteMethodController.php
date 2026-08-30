<?php

namespace Modules\Shipping\Http\Controllers\Admin\Method;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Method\DeleteMethodAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteMethodController extends Controller
{
    public function __construct(protected DeleteMethodAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
