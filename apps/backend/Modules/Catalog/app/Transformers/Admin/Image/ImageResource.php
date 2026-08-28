<?php

namespace Modules\Catalog\Transformers\Admin\Image;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Schemas\Image\ImageSchema;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ImageSchema::ID => $this->{ImageSchema::ID},
            ImageSchema::PRODUCT_ID => $this->{ImageSchema::PRODUCT_ID},
            ImageSchema::VARIANT_ID => $this->{ImageSchema::VARIANT_ID},
            ImageSchema::MEDIA_ID => $this->{ImageSchema::MEDIA_ID},
            ImageSchema::URL => $this->{ImageSchema::URL},
            ImageSchema::COLLECTION => $this->{ImageSchema::COLLECTION},
            ImageSchema::POSITION => $this->{ImageSchema::POSITION},
            ImageSchema::CREATED_AT => $this->{ImageSchema::CREATED_AT},
            ImageSchema::UPDATED_AT => $this->{ImageSchema::UPDATED_AT},
        ];
    }
}
