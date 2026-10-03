<?php

namespace Modules\Content\Http\Controllers\Public\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Public\Post\ShowPostAction;
use Modules\Content\Transformers\Public\Post\PostResource;

class ShowPostController extends Controller
{
    public function __construct(protected ShowPostAction $action) {}

    public function __invoke(string $idOrSlug, Request $request): PostResource
    {
        return PostResource::make($this->action->handle($idOrSlug));
    }
}
