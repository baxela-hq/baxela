<?php

namespace Modules\Catalog\Transformers\Admin\Featured;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;
use Modules\Catalog\Transformers\Admin\Product\ProductResource;

class FeaturedResource extends JsonResource
{
    /**
     * @param  array{product: \Illuminate\Support\Collection, category: \Illuminate\Support\Collection}  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            FeaturedItemSchema::RES_PRODUCT => ProductResource::collection($this->resource[FeaturedItemSchema::RES_PRODUCT]),
            FeaturedItemSchema::RES_CATEGORY => CategoryResource::collection($this->resource[FeaturedItemSchema::RES_CATEGORY]),
        ];
    }
}
