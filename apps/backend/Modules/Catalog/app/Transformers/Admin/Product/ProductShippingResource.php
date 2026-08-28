<?php

namespace Modules\Catalog\Transformers\Admin\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductShippingSchema as Schema;

class ProductShippingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::REQUIRES_SHIPPING => $this->resource->{Schema::REQUIRES_SHIPPING},
            Schema::WEIGHT => $this->resource->{Schema::WEIGHT},
            Schema::WEIGHT_UNIT => $this->resource->{Schema::WEIGHT_UNIT},
            Schema::PACKAGE_LENGTH => $this->resource->{Schema::PACKAGE_LENGTH},
            Schema::PACKAGE_WIDTH => $this->resource->{Schema::PACKAGE_WIDTH},
            Schema::PACKAGE_HEIGHT => $this->resource->{Schema::PACKAGE_HEIGHT},
            Schema::DIMENSION_UNIT => $this->resource->{Schema::DIMENSION_UNIT},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->resource->{Schema::UPDATED_AT},
        ];
    }
}
