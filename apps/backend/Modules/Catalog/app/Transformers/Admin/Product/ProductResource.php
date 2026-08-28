<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductSchema;
use Modules\Catalog\Transformers\Admin\Category\CategoryResource;
use Modules\Catalog\Transformers\Admin\Image\ImageResource;
use Modules\Catalog\Transformers\Admin\Option\OptionResource;
use Modules\Catalog\Transformers\Admin\Variant\VariantResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {

        return [
            ProductSchema::ID => $this->resource->{ProductSchema::ID},
            ProductSchema::TYPE => $this->resource->{ProductSchema::TYPE},
            ProductSchema::STATUS => $this->resource->{ProductSchema::STATUS},
            ProductSchema::IS_PUBLISHED => $this->resource->{ProductSchema::IS_PUBLISHED},
            ProductSchema::RES_SHIPPING => new ProductShippingResource($this->whenLoaded(ProductSchema::RES_SHIPPING)),
            ProductSchema::CREATED_AT => $this->resource->{ProductSchema::CREATED_AT},
            ProductSchema::UPDATED_AT => $this->resource->{ProductSchema::UPDATED_AT},

            ProductSchema::RES_TRANSLATIONS => ProductTranslationResource::collection($this->whenLoaded(ProductSchema::RES_TRANSLATIONS)),
            ProductSchema::RES_SEO => ProductSeoTranslationResource::collection($this->whenLoaded(ProductSchema::RES_SEO)),
            ProductSchema::RES_OPTIONS => OptionResource::collection($this->whenLoaded(ProductSchema::RES_OPTIONS)),
            ProductSchema::RES_VARIANTS => VariantResource::collection($this->whenLoaded(ProductSchema::RES_VARIANTS)),
            ProductSchema::RES_CATEGORIES => CategoryResource::collection($this->whenLoaded(ProductSchema::RES_CATEGORIES)),
            ProductSchema::RES_IMAGES => ImageResource::collection($this->whenLoaded(ProductSchema::RES_IMAGES)),
            ProductSchema::RES_ATTRIBUTE_VALUES => ProductAttributeValueResource::collection($this->whenLoaded(ProductSchema::RES_ATTRIBUTE_VALUES)),
        ];
    }
}
