<?php

namespace Modules\Catalog\Http\Controllers\Admin\Product;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\Product\DeleteProductAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteProductController extends Controller
{
    public function __construct(protected DeleteProductAction $action) {}

    public function __invoke(string $id)
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
