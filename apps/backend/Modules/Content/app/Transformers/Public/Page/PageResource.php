<?php

namespace Modules\Content\Transformers\Public\Page;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Content\Schemas\Page\PageSchema;

class PageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            PageSchema::TITLE => $this->resource->{PageSchema::TITLE},
            PageSchema::SLUG => $this->resource->{PageSchema::SLUG},
            PageSchema::CONTENT => $this->resource->{PageSchema::CONTENT},
            PageSchema::DESCRIPTION => $this->resource->{PageSchema::DESCRIPTION},
            PageSchema::CREATED_AT => $this->resource->{PageSchema::CREATED_AT},
            PageSchema::UPDATED_AT => $this->resource->{PageSchema::UPDATED_AT},
        ];
    }
}
