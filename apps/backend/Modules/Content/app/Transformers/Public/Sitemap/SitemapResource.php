<?php

namespace Modules\Content\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Groups the content module's sitemap feeds — posts, post categories
 * and pages — into one payload so consumers fetch a single endpoint.
 */
class SitemapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'posts' => PostSitemapResource::collection($this->resource['posts']),
            'post_categories' => PostCategorySitemapResource::collection($this->resource['post_categories']),
            'pages' => PageSitemapResource::collection($this->resource['pages']),
        ];
    }
}
