<?php

namespace Modules\Catalog\Transformers\Admin\Featured;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Transformers\Admin\Brand\BrandResource;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class FeaturedResource extends JsonResource
{
    /**
     * @param  array{product: Collection, category: Collection, brand: Collection}  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            FeaturedItemSchema::RES_PRODUCT => ProductResource::collection($this->resource[FeaturedItemSchema::RES_PRODUCT]),
            FeaturedItemSchema::RES_CATEGORY => CategoryResource::collection($this->resource[FeaturedItemSchema::RES_CATEGORY]),
            FeaturedItemSchema::RES_BRAND => BrandResource::collection($this->resource[FeaturedItemSchema::RES_BRAND]),
        ];
    }
}
