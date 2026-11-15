<?php

namespace Modules\Content\Transformers\Public\PostComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Models\PostComment;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;

/** @mixin PostComment */
class PostCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::BODY => $this->resource->{Schema::BODY},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::RES_USER => $this->resource->{Schema::RES_USER},
            Schema::RES_REPLIES => PostCommentResource::collection($this->whenLoaded(Schema::RES_REPLIES)),
        ];
    }
}
