<?php

namespace Modules\Shipping\Http\Controllers\Admin\Rate;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Rate\DeleteRateAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteRateController extends Controller
{
    public function __construct(protected DeleteRateAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
