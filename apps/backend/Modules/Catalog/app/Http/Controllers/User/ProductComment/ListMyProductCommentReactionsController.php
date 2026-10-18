<?php

namespace Modules\Catalog\Http\Controllers\User\ProductComment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Catalog\Actions\User\ProductComment\ListMyProductCommentReactionsAction;
use Modules\Catalog\Transformers\User\ProductComment\MyProductCommentReactionsResource;

class ListMyProductCommentReactionsController extends Controller
{
    public function __construct(protected ListMyProductCommentReactionsAction $action) {}

    public function __invoke(string $id): AnonymousResourceCollection
    {
        return MyProductCommentReactionsResource::collection($this->action->handle($id));
    }
}
