<?php

namespace Modules\Catalog\Http\Controllers\Admin\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Catalog\Actions\Admin\ProductComment\ListProductCommentAction;
use Modules\Catalog\Transformers\Admin\ProductComment\ProductCommentResource;

class ListProductCommentController extends Controller
{
    public function __construct(protected ListProductCommentAction $action) {}

    public function __invoke(Request $request)
    {
        return ProductCommentResource::collection($this->action->handle());
    }
}
