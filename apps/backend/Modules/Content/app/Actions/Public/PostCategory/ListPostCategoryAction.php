<?php

namespace Modules\Content\Actions\Public\PostCategory;

use Illuminate\Http\Request;
use Modules\Content\Schemas\PostCategory\PostCategorySchema;

class ListPostCategoryAction extends AbstractPostCategoryAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->with(PostCategorySchema::RES_TRANSLATIONS)
            ->orderByRaw(PostCategorySchema::POSITION.' IS NULL')
            ->orderBy(PostCategorySchema::POSITION)
            ->orderBy(PostCategorySchema::ID)
            ->paginate($perPage)
            ->withQueryString();
    }
}
