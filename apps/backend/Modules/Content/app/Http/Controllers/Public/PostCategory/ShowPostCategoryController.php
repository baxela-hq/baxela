<?php

namespace Modules\Content\Http\Controllers\Public\PostCategory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Content\Actions\Public\PostCategory\ShowPostCategoryAction;
use Modules\Content\Transformers\Public\PostCategory\PostCategoryResource;

class ShowPostCategoryController extends Controller
{
    public function __construct(protected ShowPostCategoryAction $action) {}

    public function __invoke(string $idOrSlug, Request $request): PostCategoryResource
    {
        return PostCategoryResource::make($this->action->handle($idOrSlug));
    }
}
