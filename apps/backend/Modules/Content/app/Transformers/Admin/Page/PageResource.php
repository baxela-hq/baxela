<?php

namespace Modules\Content\Transformers\Admin\Page;

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
            PageSchema::ID => $this->{PageSchema::ID},
            PageSchema::STATUS => $this->{PageSchema::STATUS},
            PageSchema::CREATED_AT => $this->{PageSchema::CREATED_AT},
            PageSchema::UPDATED_AT => $this->{PageSchema::UPDATED_AT},
            PageSchema::RES_TRANSLATIONS => PageTranslationResource::collection($this->whenLoaded(PageSchema::RES_TRANSLATIONS)),
        ];
    }
}
