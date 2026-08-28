<?php

namespace Modules\User\Transformers\User\Profile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\User\Schemas\Profile\ProfileSchema;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            ProfileSchema::FIRST_NAME => $this->resource?->{ProfileSchema::FIRST_NAME},
            ProfileSchema::LAST_NAME => $this->resource?->{ProfileSchema::LAST_NAME},
        ];
    }
}
