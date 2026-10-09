<?php

namespace Modules\Content\Transformers\Admin\Post;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Post\PostProductSchema;
use Modules\Core\Contracts\Gateways\Catalog\DTOs\ProductSummary;

class PostProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource->getAttribute(PostProductSchema::ATTR_PRODUCT_SUMMARY);

        return [
            'id' => $this->resource->{PostProductSchema::PRODUCT_ID},
            'title' => $summary instanceof ProductSummary ? $summary->title : null,
            'image_url' => $summary instanceof ProductSummary ? $summary->image_url : null,
        ];
    }
}
