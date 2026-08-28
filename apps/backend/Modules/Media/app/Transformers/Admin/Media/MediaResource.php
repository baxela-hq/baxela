<?php

namespace Modules\Media\Transformers\Admin\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Schemas\Media\MediaSchema;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            MediaSchema::ID => $this->resource->{MediaSchema::ID},
            MediaSchema::USER_ID => $this->resource->{MediaSchema::USER_ID},
            MediaSchema::FOLDER_ID => $this->resource->{MediaSchema::FOLDER_ID},
            MediaSchema::DISK => $this->resource->{MediaSchema::DISK},
            MediaSchema::RES_URL => Storage::url($this->resource->{MediaSchema::PATH}),
            MediaSchema::PATH => $this->resource->{MediaSchema::PATH},
            MediaSchema::NAME => $this->resource->{MediaSchema::NAME},
            MediaSchema::FILENAME => $this->resource->{MediaSchema::FILENAME},
            MediaSchema::EXTENSION => $this->resource->{MediaSchema::EXTENSION},
            MediaSchema::MIME_TYPE => $this->resource->{MediaSchema::MIME_TYPE},
            MediaSchema::SIZE => $this->resource->{MediaSchema::SIZE},
            MediaSchema::METADATA => $this->resource->{MediaSchema::METADATA},
            MediaSchema::CREATED_AT => $this->resource->{MediaSchema::CREATED_AT},
            MediaSchema::UPDATED_AT => $this->resource->{MediaSchema::UPDATED_AT},
        ];
    }
}
