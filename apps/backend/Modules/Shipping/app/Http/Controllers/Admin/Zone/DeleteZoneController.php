<?php

namespace Modules\Shipping\Http\Controllers\Admin\Zone;

use App\Http\Controllers\Controller;
use Modules\Shipping\Actions\Admin\Zone\DeleteZoneAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteZoneController extends Controller
{
    public function __construct(protected DeleteZoneAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
