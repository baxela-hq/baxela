<?php

namespace Modules\Content\Transformers\Admin\Featured;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Modules\Content\Models\FeaturedItem;
use Modules\Content\Schemas\FeaturedItem\FeaturedItemSchema;
use Modules\Content\Transformers\Admin\Post\PostResource;

/** @mixin FeaturedItem */
class FeaturedResource extends JsonResource
{
    /**
     * @param  array{post: Collection}  $resource
     */
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            FeaturedItemSchema::RES_POST => PostResource::collection($this->resource[FeaturedItemSchema::RES_POST]),
        ];
    }
}
