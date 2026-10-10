<?php

namespace Modules\Catalog\Actions\Public\Brand;

use Illuminate\Http\Request;
use Modules\Catalog\Models\FeaturedItem;
use Modules\Catalog\Schemas\Brand\BrandSchema;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;

class ListBrandAction extends AbstractBrandAction
{
    public function handle(Request $request)
    {
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return $this->model
            ->with(BrandSchema::RES_TRANSLATIONS)
            ->when($request->boolean('featured'), fn ($query) => $query
                ->whereHas('featuredItem')
                // Featured listings follow the admin-managed position.
                ->orderBy(
                    FeaturedItem::query()
                        ->select(FeaturedItemSchema::POSITION)
                        ->where(FeaturedItemSchema::FEATUREDABLE_TYPE, FeaturedItemSchema::TYPE_BRAND)
                        ->whereColumn(
                            FeaturedItemSchema::FEATUREDABLE_ID,
                            BrandSchema::TABLE.'.'.BrandSchema::ID
                        )
                ), fn ($query) => $query
                ->orderByRaw(BrandSchema::POSITION.' IS NULL')
                ->orderBy(BrandSchema::POSITION)
                ->orderBy(BrandSchema::ID))
            ->paginate($perPage)
            ->withQueryString();
    }
}
