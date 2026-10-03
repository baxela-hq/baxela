<?php

namespace Modules\Content\Transformers\Admin\PostComment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostSchema as PSchema;
use Modules\Content\Schemas\PostComment\PostCommentSchema as Schema;
use Modules\Content\Transformers\Admin\Post\PostTranslationResource;

class PostCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            Schema::ID => $this->resource->{Schema::ID},
            Schema::POST_ID => $this->resource->{Schema::POST_ID},
            Schema::PARENT_ID => $this->resource->{Schema::PARENT_ID},
            Schema::USER_ID => $this->resource->{Schema::USER_ID},
            Schema::BODY => $this->resource->{Schema::BODY},
            Schema::STATUS => $this->resource->{Schema::STATUS},
            Schema::CREATED_AT => $this->resource->{Schema::CREATED_AT},
            Schema::UPDATED_AT => $this->resource->{Schema::UPDATED_AT},
            Schema::RES_USER => $this->resource->{Schema::RES_USER},
            Schema::RES_POST => $this->whenLoaded(Schema::RES_POST, function () {
                return [
                    PSchema::ID => $this->resource->{Schema::RES_POST}->{PSchema::ID},
                    PSchema::RES_TRANSLATIONS => PostTranslationResource::collection(
                        $this->resource->{Schema::RES_POST}->{PSchema::RES_TRANSLATIONS}
                    ),
                ];
            }),
            Schema::RES_REPLIES => PostCommentResource::collection($this->whenLoaded(Schema::RES_REPLIES)),
        ];
    }
}
