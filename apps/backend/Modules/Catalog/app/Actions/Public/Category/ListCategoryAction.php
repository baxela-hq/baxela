<?php

namespace Modules\Catalog\Actions\Public\Category;

use Illuminate\Http\Request;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Category\CategorySchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;

class ListCategoryAction extends AbstractCategoryAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->with(CategorySchema::RES_TRANSLATIONS)
            ->when($request->boolean('featured'), fn ($query) => $query
                ->whereHas('featuredItem')
                // Featured listings follow the admin-managed position.
                ->orderBy(
                    FeaturedItem::query()
                        ->select(FeaturedItemSchema::POSITION)
                        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_CATEGORY)
                        ->whereColumn(
                            FeaturedItemSchema::FEATUREDABLE_ID,
                            CategorySchema::TABLE.'.'.CategorySchema::ID
                        )
                ), fn ($query) => $query
                ->orderByRaw(CategorySchema::POSITION.' IS NULL')
                ->orderBy(CategorySchema::POSITION)
                ->orderBy(CategorySchema::ID))
            ->paginate($perPage)
            ->withQueryString();
    }
}
