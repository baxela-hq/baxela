<?php

namespace Modules\Catalog\Transformers\Public\ProductComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;

class ProductCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::BODY => $this->resource->{Schema::BODY},
            Schema::RATING => $this->resource->{Schema::RATING},
            // Attached by the list action's withCount; replies load them the same way
            Schema::RES_LIKES_COUNT => $this->resource->{Schema::RES_LIKES_COUNT} ?? 0,
            Schema::RES_DISLIKES_COUNT => $this->resource->{Schema::RES_DISLIKES_COUNT} ?? 0,
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::RES_USER => $this->resource->{Schema::RES_USER},
            Schema::RES_REPLIES => ProductCommentResource::collection($this->whenLoaded(Schema::RES_REPLIES)),
        ];
    }
}
