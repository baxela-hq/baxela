<?php

namespace Modules\Content\Transformers\Public\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostImageSchema;

class PostImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PostImageSchema::ID => $this->{PostImageSchema::ID},
            PostImageSchema::MEDIA_ID => $this->{PostImageSchema::MEDIA_ID},
            PostImageSchema::URL => $this->{PostImageSchema::URL},
            PostImageSchema::COLLECTION => $this->{PostImageSchema::COLLECTION},
            PostImageSchema::POSITION => $this->{PostImageSchema::POSITION},
        ];
    }
}
