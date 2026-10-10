<?php

namespace Modules\Catalog\Transformers\Admin\Brand;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Brand\BrandSchema;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            BrandSchema::ID => $this->{BrandSchema::ID},
            BrandSchema::POSITION => $this->{BrandSchema::POSITION},
            BrandSchema::IMAGE_MEDIA_ID => $this->{BrandSchema::IMAGE_MEDIA_ID},
            BrandSchema::IMAGE_URL => $this->{BrandSchema::IMAGE_URL},
            BrandSchema::CREATED_AT => $this->{BrandSchema::CREATED_AT},
            BrandSchema::UPDATED_AT => $this->{BrandSchema::UPDATED_AT},
            BrandSchema::RES_TRANSLATIONS => BrandTranslationResource::collection($this->whenLoaded(BrandSchema::RES_TRANSLATIONS)),
        ];
    }
}
