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
            ProfileSchema::FULL_NAME => $this->resource?->{ProfileSchema::FULL_NAME},
            ProfileSchema::DISPLAY_NAME => $this->resource?->{ProfileSchema::DISPLAY_NAME},
            ProfileSchema::BIO => $this->resource?->{ProfileSchema::BIO},
            ProfileSchema::AVATAR => $this->resource?->{ProfileSchema::AVATAR},
            ProfileSchema::GENDER => $this->resource?->{ProfileSchema::GENDER},
            ProfileSchema::DATE_OF_BIRTH => $this->resource?->{ProfileSchema::DATE_OF_BIRTH},
        ];
    }
}
