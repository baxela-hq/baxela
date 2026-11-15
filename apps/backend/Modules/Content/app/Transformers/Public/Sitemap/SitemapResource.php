<?php

namespace Modules\Content\Transformers\Public\Sitemap;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Models\Page;

/**
 * Groups the content module's sitemap feeds — posts, post categories
 * and pages — into one payload so consumers fetch a single endpoint.
 */
/** @mixin Page */
class SitemapResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'posts' => PostSitemapResource::collection($this->resource['posts']),
            'post_categories' => PostCategorySitemapResource::collection($this->resource['post_categories']),
            'pages' => PageSitemapResource::collection($this->resource['pages']),
        ];
    }
}
