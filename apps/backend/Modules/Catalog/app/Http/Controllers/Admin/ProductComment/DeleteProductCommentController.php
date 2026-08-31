<?php

namespace Modules\Catalog\Http\Controllers\Admin\ProductComment;

use App\Http\Controllers\Controller;
use Modules\Catalog\Actions\Admin\ProductComment\DeleteProductCommentAction;
use Symfony\Component\HttpFoundation\Response;

class DeleteProductCommentController extends Controller
{
    public function __construct(protected DeleteProductCommentAction $action) {}

    public function __invoke(string $id): Response
    {
        $this->action->handle($id);

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }
}
