<?php

namespace Modules\Catalog\Http\Controllers\Admin\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\ProductComment\ShowProductCommentAction;
use Modules\Catalog\Transformers\Admin\ProductComment\ProductCommentResource;

class ShowProductCommentController extends Controller
{
    public function __construct(protected ShowProductCommentAction $action) {}

    public function __invoke(string $id, Request $request): ProductCommentResource
    {
        return ProductCommentResource::make($this->action->handle($id));
    }
}
