<?php

namespace Modules\Catalog\Http\Controllers\Public\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Public\ProductComment\ListProductCommentsAction;
use Modules\Catalog\Transformers\Public\ProductComment\ProductCommentResource;

class ListProductCommentsController extends Controller
{
    public function __construct(protected ListProductCommentsAction $action) {}

    public function __invoke(string $id, Request $request)
    {
        return ProductCommentResource::collection($this->action->handle($id));
    }
}
