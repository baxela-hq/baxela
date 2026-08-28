<?php

namespace Modules\Auth\Transformers\Public\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Schemas\User\UserSchema;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            UserSchema::ID => $this->resource->{UserSchema::ID},
            UserSchema::EMAIL => $this->resource->{UserSchema::EMAIL},
            UserSchema::EMAIL_VERIFIED_AT => $this->resource->{UserSchema::EMAIL_VERIFIED_AT},
            UserSchema::IS_ACTIVE => $this->resource->{UserSchema::IS_ACTIVE},
            UserSchema::IS_ADMIN => $this->resource->{UserSchema::IS_ADMIN},
            UserSchema::COMMENT => $this->resource->{UserSchema::COMMENT},
        ];
    }
}
