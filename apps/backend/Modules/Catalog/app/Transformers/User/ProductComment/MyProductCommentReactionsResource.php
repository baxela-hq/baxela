<?php

namespace Modules\Catalog\Transformers\User\ProductComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionSchema as RSchema;

class MyProductCommentReactionsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            RSchema::RES_COMMENT_ID => $this->resource->{RSchema::PRODUCT_COMMENT_ID},
            RSchema::REACTION => $this->resource->{RSchema::REACTION},
        ];
    }
}
