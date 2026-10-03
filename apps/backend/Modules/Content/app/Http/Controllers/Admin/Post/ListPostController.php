<?php

namespace Modules\Content\Http\Controllers\Admin\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Content\Actions\Admin\Post\ListPostAction;
use Modules\Content\Transformers\Admin\Post\PostResource;

class ListPostController extends Controller
{
    public function __construct(protected ListPostAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return PostResource::collection($this->action->handle());
    }
}
