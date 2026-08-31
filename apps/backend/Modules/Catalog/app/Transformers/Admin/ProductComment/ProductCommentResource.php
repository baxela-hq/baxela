<?php

namespace Modules\Catalog\Transformers\Admin\ProductComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Product\ProductSchema as PSchema;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;
use Modules\Catalog\Transformers\Admin\Product\ProductTranslationResource;

class ProductCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::PRODUCT_ID => $this->resource->{Schema::PRODUCT_ID},
            Schema::PARENT_ID => $this->resource->{Schema::PARENT_ID},
            Schema::USER_ID => $this->resource->{Schema::USER_ID},
            Schema::BODY => $this->resource->{Schema::BODY},
            Schema::STATUS => $this->resource->{Schema::STATUS},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->resource->{Schema::UPDATED_AT},
            Schema::RES_USER => $this->resource->{Schema::RES_USER},
            Schema::RES_PRODUCT => $this->whenLoaded(Schema::RES_PRODUCT, function () {
                return [
                    PSchema::ID => $this->resource->{Schema::RES_PRODUCT}->{PSchema::ID},
                    PSchema::RES_TRANSLATIONS => ProductTranslationResource::collection(
                        $this->resource->{Schema::RES_PRODUCT}->{PSchema::RES_TRANSLATIONS}
                    ),
                ];
            }),
            Schema::RES_REPLIES => ProductCommentResource::collection($this->whenLoaded(Schema::RES_REPLIES)),
        ];
    }
}
