<?php

namespace Modules\Catalog\Http\Controllers\Admin\Brand;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Brand\DeleteBrandAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteBrandController extends Controller
{
    public function __construct(protected DeleteBrandAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
