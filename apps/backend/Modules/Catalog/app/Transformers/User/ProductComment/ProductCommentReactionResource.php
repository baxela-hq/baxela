<?php

namespace Modules\Catalog\Transformers\User\ProductComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\ProductComment\ProductCommentReactionEnum;
use Modules\Catalog\Schemas\ProductComment\ProductCommentSchema as Schema;

class ProductCommentReactionResource extends JsonResource
{
    /**
     * @param  array{reaction: ?ProductCommentReactionEnum, likes_count: int, dislikes_count: int}  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'reaction' => $this->resource['reaction']?->value,
            Schema::RES_LIKES_COUNT => $this->resource['likes_count'],
            Schema::RES_DISLIKES_COUNT => $this->resource['dislikes_count'],
        ];
    }
}
